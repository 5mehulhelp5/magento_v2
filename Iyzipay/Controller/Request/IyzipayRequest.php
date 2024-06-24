<?php

/**
 * iyzico Payment Gateway For Magento 2
 * Copyright (C) 2018 iyzico
 *
 * This file is part of Iyzico/Iyzipay.
 *
 * Iyzico/Iyzipay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Iyzico\Iyzipay\Controller\Request;

use Exception;
use Iyzico\Iyzipay\Helper\CookieHelper;
use Iyzico\Iyzipay\Helper\ObjectHelper;
use Iyzico\Iyzipay\Helper\PkiStringBuilder;
use Iyzico\Iyzipay\Helper\PriceHelper;
use Iyzico\Iyzipay\Helper\RequestHelper;
use Iyzico\Iyzipay\Helper\StringHelper;
use Iyzico\Iyzipay\Logger\IyziLogger;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class IyzipayRequest extends Action
{

    protected Context $_context;
    protected CheckoutSession $_checkoutSession;
    protected CustomerSession $_customerSession;
    protected ScopeConfigInterface $_scopeConfig;
    protected IyziCardFactory $_iyziCardFactory;
    protected StoreManagerInterface $_storeManager;
    protected StringHelper $_stringHelper;
    protected PriceHelper $_priceHelper;
    protected JsonFactory $_resultJsonFactory;
    protected Quote $_quote;
    protected CartManagementInterface $_cartManagement;
    protected IyziLogger $_iyziLogger;
    protected OrderRepositoryInterface $_orderRepository;

    public function __construct
    (
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        IyziCardFactory $iyziCardFactory,
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $orderRepository,
        StringHelper $stringHelper,
        PriceHelper $priceHelper,
        JsonFactory $resultJsonFactory,
        Quote $quote,
        IyziLogger $iyziLogger
    ) {
        $this->_context = $context;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_iyziCardFactory = $iyziCardFactory;
        $this->_stringHelper = $stringHelper;
        $this->_priceHelper = $priceHelper;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_quote = $quote;
        $this->_cartManagement = $cartManagement;
        $this->_orderRepository = $orderRepository;
        $this->_iyziLogger = $iyziLogger;
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * This function is responsible for executing the payment request.
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute()
    {
        return $this->processPaymentRequest();
    }

    /**
     * Process Payment Request
     *
     * This function is responsible for processing the payment request.
     *
     * @return Json
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function processPaymentRequest()
    {
        $defination = $this->getPaymentDefinition();

        $objectHelper = $this->getObjectHelper();
        $pkiStringBuilder = $this->getPkiStringBuilder();
        $requestHelper = $this->getRequestHelper();
        $cookieHelper = $this->getCookieHelper();

        $postData = $this->getRequest()->getPostValue();
        $customerMail = $postData['iyziQuoteEmail'] ?? null;
        $customerBasketId = $postData['iyziQuoteId'] ?? null;
        $checkoutSession = $this->_checkoutSession->getQuote();
        $storeId = $this->_storeManager->getStore()->getId();
        $locale = $this->getLocale($storeId);
        $currency = $this->getCurrency();
        $callBack = $this->getCallbackUrl();
        $quoteId = $checkoutSession->getId();
        $magentoVersion = $this->getMagentoVersion();

        $cookieHelper->ensureCookiesSameSite();
        $conversationId = $this->generateConversationId($quoteId);

        $defination['customerId'] = $this->getCustomerId();
        $defination['customerCardUserKey'] = $this->getCustomerCardUserKey($defination['customerId'], $defination['apiKey']);

        if (isset($customerMail) && isset($customerBasketId)) {
            $this->storeSessionData($customerMail, $customerBasketId);
        }

        $iyzico = $this->createPaymentOption($objectHelper, $checkoutSession, $defination['customerCardUserKey'], $locale, $conversationId, $currency, $quoteId, $callBack, $magentoVersion);
        $orderObject = $pkiStringBuilder->sortFormObject($iyzico);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($orderObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);

        $iyzicoJson = json_encode($iyzico, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestResponse = $requestHelper->sendCheckoutFormRequest($defination['baseUrl'], $iyzicoJson, $authorization);

        $result = $this->handleRequestResponse($requestResponse, $currency);

        return $this->createJsonResult($result);
    }

    /**
     * Get Payment Definition
     *
     * This function is responsible for getting the payment definition.
     *
     * @return array
     */
    private function getPaymentDefinition()
    {
        return [
            'rand' => uniqid(),
            'customerId' => 0,
            'customerCardUserKey' => '',
            'baseUrl' => $this->_scopeConfig->getValue('payment/iyzipay/sandbox') ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->_scopeConfig->getValue('payment/iyzipay/api_key'),
            'secretKey' => $this->_scopeConfig->getValue('payment/iyzipay/secret_key'),
        ];
    }

    /**
     * Get Object Helper
     *
     * This function is responsible for getting the object helper.
     *
     * @return ObjectHelper
     */
    private function getObjectHelper()
    {
        return new ObjectHelper($this->_stringHelper, $this->_priceHelper);
    }

    /**
     * Get Pki String Builder
     *
     * This function is responsible for getting the pki string builder.
     *
     * @return PkiStringBuilder
     */
    private function getPkiStringBuilder()
    {
        return new PkiStringBuilder();
    }

    /**
     * Get Request Helper
     *
     * This function is responsible for getting the request helper.
     *
     * @return RequestHelper
     */
    private function getRequestHelper()
    {
        return new RequestHelper();
    }

    /**
     * Get Cookie Helper
     *
     * This function is responsible for getting the cookie helper.
     *
     * @return CookieHelper
     */
    private function getCookieHelper()
    {
        return new CookieHelper();
    }

    /**
     * Get Locale
     *
     * This function is responsible for getting the locale.
     *
     * @param int $storeId
     * @return string
     */
    private function getLocale($storeId)
    {
        return $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Currency
     *
     * This function is responsible for getting the currency.
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getCurrency()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get Callback Url
     *
     * This function is responsible for getting the callback url.
     *
     * @throws NoSuchEntityException
     */
    private function getCallbackUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }

    /**
     * Get Magento Version
     *
     * This function is responsible for getting the magento version.
     */
    private function getMagentoVersion()
    {
        $objectManager = ObjectManager::getInstance();
        $productMetaData = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetaData->getVersion();
    }

    /**
     * Generate Conversation Id
     *
     * This function is responsible for generating the conversation id.
     *
     * @param $quoteId
     * @return string
     */
    private function generateConversationId($quoteId)
    {
        return $this->_stringHelper->generateConversationId($quoteId);
    }

    /**
     * Handle Request Response
     *
     * This function is responsible for handling the request response.
     *
     * @return int|null
     */
    private function getCustomerId()
    {
        return $this->_customerSession->isLoggedIn() ? $this->_customerSession->getCustomerId() : 0;
    }

    /**
     * Get Customer Card User Key
     *
     * This function is responsible for getting the customer card user key.
     *
     * @param $customerId
     * @param $apiKey
     * @return string
     */
    private function getCustomerCardUserKey(int $customerId, string $apiKey)
    {
        if ($customerId) {
            $iyziCardFind = $this->_iyziCardFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('api_key', $apiKey)
                ->addFieldToSelect('card_user_key');
            $iyziCardFind = $iyziCardFind->getData();
            return !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : '';
        }
        return '';
    }

    /**
     * Store Session Data
     *
     * This function is responsible for storing the session data.
     *
     * @param $customerMail
     * @param $customerBasketId
     * @return void
     */
    private function storeSessionData($customerMail, $customerBasketId)
    {
        $this->_customerSession->setEmail($customerMail);
        $this->_checkoutSession->setGuestQuoteId($customerBasketId);
    }

    /**
     * Create Payment Option
     *
     * This function is responsible for creating the payment option.
     *
     * @param $objectHelper
     * @param $checkoutSession
     * @param $customerCardUserKey
     * @param $locale
     * @param $conversationId
     * @param $currency
     * @param $quoteId
     * @param $callBack
     * @param $magentoVersion
     */
    private function createPaymentOption($objectHelper, $checkoutSession, $customerCardUserKey, $locale, $conversationId, $currency, $quoteId, $callBack, $magentoVersion)
    {
        $iyzico = $objectHelper->createPaymentOption($checkoutSession, $customerCardUserKey, $locale, $conversationId, $currency, $quoteId, $callBack, $magentoVersion);
        $iyzico->buyer = $objectHelper->createBuyerObject($checkoutSession, $this->_customerSession->getEmail());
        $iyzico->billingAddress = $objectHelper->createBillingAddressObject($checkoutSession);
        $iyzico->shippingAddress = $objectHelper->createShippingAddressObject($checkoutSession);
        $iyzico->basketItems = $objectHelper->createBasketItems($checkoutSession);
        return $iyzico;
    }

    /**
     * Handle Request Response
     *
     * This function is responsible for handling the request response.
     *
     * @param $requestResponse
     * @param $currency
     * @return array
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function handleRequestResponse($requestResponse, $currency)
    {
        if (isset($requestResponse->status) && $requestResponse->status == 'success') {
            return $this->processSuccessfulResponse($requestResponse, $currency);
        } elseif (isset($requestResponse->errorCode)) {
            return $this->processErrorResponse($requestResponse);
        } elseif ($requestResponse === null) {
            return $this->processNullResponse();
        }

        $this->_iyziLogger->critical("result must be an array.", ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        return [
            'success' => false,
            'redirect' => 'checkout/error',
            'errorCode' => '0',
            'errorMessage' => 'Check the Logs for more information.'
        ];
    }

    /**
     * Process Successful Response
     *
     * This function is responsible for processing the successful response.
     *
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    private function processSuccessfulResponse($requestResponse, $currency)
    {
        $this->_quote = $this->_checkoutSession->getQuote();
        $orderId = $this->placeOrder();
        $quoteId = $this->_quote->getId();

        $this->saveIyziOrderTable($requestResponse, $orderId, $quoteId);
        return ['success' => true, 'url' => $requestResponse->paymentPageUrl];
    }

    /**
     * Place Order
     *
     * This function is responsible for placing the order and setting the status to pending_payment.
     *
     * @throws CouldNotSaveException
     */
    private function placeOrder()
    {
        if ($this->_customerSession->isLoggedIn()) {
            $orderId = $this->_cartManagement->placeOrder($this->_quote->getId());
        } else {
            $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
            $this->_quote->setCustomerEmail($this->_customerSession->getEmail());
            $orderId = $this->_cartManagement->placeOrder($this->_quote->getId());
        }

        $order = $this->_orderRepository->get($orderId);
        $comment = __("START_ORDER");

        $order->setState('received')->setStatus('received');
        $order->addStatusHistoryComment($comment);
        $order->getPayment()->setMethod('iyzipay');

        $this->_orderRepository->save($order);

        return $orderId;
    }

    /**
     * Save Iyzi Order Table
     *
     * This function is responsible for saving the iyzi order table.
     *
     * @param $requestResponse
     * @param $orderId
     *
     * @throws CouldNotSaveException
     */
    private function saveIyziOrderTable($requestResponse, $orderId, $quoteId)
    {
        $iyzicoOrderJob = $this->_objectManager->create('Iyzico\Iyzipay\Model\IyziOrderJob');

        $iyzicoOrderJob->setData([
            'order_id' => $orderId,
            'quote_id' => $quoteId,
            'iyzico_payment_token' => $requestResponse->token,
            'iyzico_conversationId' => $requestResponse->conversationId,
            'expire_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        try {
            $iyzicoOrderJob->save();
        } catch (Exception $e) {
            $this->_iyziLogger->critical($e->getMessage());
        }
    }

    /**
     * Process Error Response
     *
     * This function is responsible for processing the error response.
     *
     * @param $requestResponse
     * @return array
     */
    private function processErrorResponse($requestResponse)
    {
        $this->_iyziLogger->critical(
            "Error Code: " . $requestResponse->errorCode . " Error Message: " . $requestResponse->errorMessage,
            ['fileName' => __FILE__, 'lineNumber' => __LINE__]
        );

        return [
            'success' => false,
            'redirect' => 'checkout/error',
            'errorCode' => $requestResponse->errorCode,
            'errorMessage' => $requestResponse->errorMessage
        ];
    }

    /**
     * Process Null Response
     *
     * This function is responsible for processing the null response.
     *
     * @return array
     */
    private function processNullResponse()
    {
        $this->_iyziLogger->critical(
            "requestResponse must not be NULL",
            ['fileName' => __FILE__, 'lineNumber' => __LINE__]
        );

        return [
            'success' => false,
            'redirect' => 'checkout/error',
            'errorCode' => '0',
            'errorMessage' => 'Check the Logs for more information.'
        ];
    }

    /**
     * Create Json Result
     *
     * This function is responsible for creating the json result.
     *
     * @param $result
     * @return Json
     */
    private function createJsonResult($result)
    {
        $resultJson = $this->_resultJsonFactory->create();
        return $resultJson->setData($result);
    }

}
