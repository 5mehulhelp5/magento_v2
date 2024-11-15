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
use Iyzico\Iyzipay\Helper\PkiStringBuilderFactory;
use Iyzico\Iyzipay\Helper\PriceHelper;
use Iyzico\Iyzipay\Helper\RequestHelper;
use Iyzico\Iyzipay\Helper\RequestHelperFactory;
use Iyzico\Iyzipay\Helper\StringHelper;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
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
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\ItemFactory as QuoteItemFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class IyzipayRequest extends Action
{

    protected Context $context;
    protected CheckoutSession $checkoutSession;
    protected CustomerSession $customerSession;
    protected ScopeConfigInterface $scopeConfig;
    protected IyziCardFactory $iyziCardFactory;
    protected StoreManagerInterface $storeManager;
    protected StringHelper $stringHelper;
    protected PriceHelper $priceHelper;
    protected JsonFactory $resultJsonFactory;
    protected Quote $quote;
    protected CartManagementInterface $cartManagement;
    protected IyziErrorLogger $errorLogger;
    protected OrderRepositoryInterface $orderRepository;
    protected PkiStringBuilderFactory $pkiStringBuilderFactory;
    protected RequestHelperFactory $requestHelperFactory;
    protected QuoteFactory $quoteFactory;
    protected QuoteItemFactory $quoteItemFactory;
    protected AddressFactory $quoteAddressFactory;
    protected ProductRepositoryInterface $productRepository;
    protected CustomerRepositoryInterface $customerRepository;

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
        IyziErrorLogger $errorLogger,
        PkiStringBuilderFactory $pkiStringBuilderFactory,
        RequestHelperFactory $requestHelperFactory,
        QuoteFactory $quoteFactory,
        QuoteItemFactory $quoteItemFactory,
        AddressFactory $quoteAddressFactory,
        ProductRepositoryInterface $productRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->iyziCardFactory = $iyziCardFactory;
        $this->stringHelper = $stringHelper;
        $this->priceHelper = $priceHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quote = $quote;
        $this->cartManagement = $cartManagement;
        $this->orderRepository = $orderRepository;
        $this->errorLogger = $errorLogger;
        $this->pkiStringBuilderFactory = $pkiStringBuilderFactory;
        $this->requestHelperFactory = $requestHelperFactory;
        $this->quoteFactory = $quoteFactory;
        $this->quoteItemFactory = $quoteItemFactory;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->productRepository = $productRepository;
        $this->customerRepository = $customerRepository;
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
    public function execute($order = null): Json
    {
        if ($order) {
            return $this->processPaymentRequest($order);
        }
        return $this->processPaymentRequest();
    }


    /**
     * Process Payment Request
     *
     * This function is responsible for processing the payment request.
     *
     * @param  null  $order
     * @return Json
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function processPaymentRequest($order = null): Json
    {
        $defination = $this->getPaymentDefinition();

        $objectHelper = $this->getObjectHelper();
        $pkiStringBuilder = $this->getPkiStringBuilder();
        $requestHelper = $this->getRequestHelper();
        $cookieHelper = $this->getCookieHelper();

        if ($order) {
            $checkoutSession = $this->createQuoteFromOrder($order);
            $customerMail = $order->getCustomerEmail();
            $customerBasketId = $order->getQuoteId();
            $quoteId = $order->getQuoteId();
            $orderId = $order->getId();
        } else {
            $postData = $this->getRequest()->getPostValue();
            $customerMail = $postData['iyziQuoteEmail'] ?? null;
            $customerBasketId = $postData['iyziQuoteId'] ?? null;
            $checkoutSession = $this->checkoutSession->getQuote();
            $quoteId = $checkoutSession->getId();
            $orderId = $this->placeOrder();
        }

        $websiteId = $this->storeManager->getWebsite()->getId();
        $locale = $this->getLocale($websiteId);
        $currency = $this->getCurrency();
        $callBack = $this->getCallbackUrl();
        $magentoVersion = $this->getMagentoVersion();

        $cookieHelper->ensureCookiesSameSite();
        $conversationId = $this->generateConversationId($quoteId);

        $defination['customerId'] = $this->getCustomerId();
        $defination['customerCardUserKey'] = $this->getCustomerCardUserKey($defination['customerId'],
            $defination['apiKey']);

        if (isset($customerMail) && isset($customerBasketId)) {
            $this->storeSessionData($customerMail, $customerBasketId);
        }

        $iyzico = $this->createPaymentOption($objectHelper, $checkoutSession, $defination['customerCardUserKey'],
            $locale, $conversationId, $currency, $quoteId, $orderId, $callBack, $magentoVersion);
        $orderObject = $pkiStringBuilder->sortFormObject($iyzico);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($orderObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'],
            $defination['secretKey'], $defination['rand']);

        $iyzicoJson = json_encode($iyzico, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestResponse = $requestHelper->sendCheckoutFormRequest($defination['baseUrl'], $iyzicoJson, $authorization);

        if ($order) {
            return $requestResponse;
        }

        $result = $this->handleRequestResponse($requestResponse, $orderId);

        return $this->createJsonResult($result);
    }

    /**
     * Get Payment Definition
     *
     * This function is responsible for getting the payment definition.
     *
     * @return array
     * @throws LocalizedException
     */
    private function getPaymentDefinition(): array
    {
        $websiteId = $this->storeManager->getWebsite()->getId();

        return [
            'rand' => uniqid(),
            'customerId' => 0,
            'customerCardUserKey' => '',
            'baseUrl' => $this->scopeConfig->getValue(
                'payment/iyzipay/sandbox',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            ) ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->scopeConfig->getValue(
                'payment/iyzipay/api_key',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            ),
            'secretKey' => $this->scopeConfig->getValue(
                'payment/iyzipay/secret_key',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            ),
        ];
    }

    /**
     * Get Object Helper
     *
     * This function is responsible for getting the object helper.
     *
     * @return ObjectHelper
     */
    private function getObjectHelper(): ObjectHelper
    {
        return new ObjectHelper($this->stringHelper, $this->priceHelper);
    }

    /**
     * Get Pki String Builder
     *
     * This function is responsible for getting the pki string builder.
     *
     * @return PkiStringBuilder
     */
    private function getPkiStringBuilder(): PkiStringBuilder
    {
        return $this->pkiStringBuilderFactory->create();
    }

    /**
     * Get Request Helper
     *
     * This function is responsible for getting the request helper.
     *
     * @return RequestHelper
     */
    private function getRequestHelper(): RequestHelper
    {
        return $this->requestHelperFactory->create();
    }

    /**
     * Get Cookie Helper
     *
     * This function is responsible for getting the cookie helper.
     *
     * @return CookieHelper
     */
    private function getCookieHelper(): CookieHelper
    {
        return new CookieHelper();
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function createQuoteFromOrder($order): Quote
    {
        $quote = $this->quoteFactory->create();
        $quote->setStoreId($order->getStoreId());

        // Add items to quote
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $this->productRepository->getById($item->getProductId());
            $quoteItem = $this->quoteItemFactory->create();
            $quoteItem->setProduct($product);
            $quoteItem->setQty($item->getQtyOrdered());
            $quote->addItem($quoteItem);
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            $quoteBillingAddress = $this->quoteAddressFactory->create();
            $this->copyAddressData($billingAddress, $quoteBillingAddress);
            $quote->setBillingAddress($quoteBillingAddress);
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $quoteShippingAddress = $this->quoteAddressFactory->create();
            $this->copyAddressData($shippingAddress, $quoteShippingAddress);
            $quote->setShippingAddress($quoteShippingAddress);
        }

        $quote->setCustomerEmail($order->getCustomerEmail());
        $quote->setCustomerIsGuest($order->getCustomerIsGuest());

        if (!$order->getCustomerIsGuest()) {
            $customer = $this->customerRepository->getById($order->getCustomerId());
            $quote->setCustomer($customer);
        }

        $payment = $order->getPayment();
        if ($payment) {
            $quotePayment = $quote->getPayment();
            $quotePayment->setMethod($payment->getMethod());
        }

        $quote->collectTotals();

        return $quote;
    }

    private function copyAddressData($sourceAddress, $targetAddress): void
    {
        $targetAddress->setFirstname($sourceAddress->getFirstname());
        $targetAddress->setLastname($sourceAddress->getLastname());
        $targetAddress->setStreet($sourceAddress->getStreet());
        $targetAddress->setCity($sourceAddress->getCity());
        $targetAddress->setCountryId($sourceAddress->getCountryId());
        $targetAddress->setPostcode($sourceAddress->getPostcode());
        $targetAddress->setTelephone($sourceAddress->getTelephone());
        $targetAddress->setRegion($sourceAddress->getRegion());
        $targetAddress->setRegionCode($sourceAddress->getRegionCode());
        $targetAddress->setRegionId($sourceAddress->getRegionId());
        $targetAddress->setCompany($sourceAddress->getCompany());
    }

    /**
     * Handle Request Response
     *
     * This function is responsible for handling the request response.
     *
     * @return int|null
     */
    private function getCustomerId(): ?int
    {
        return $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerId() : 0;
    }

    /**
     * Place Order
     *
     * This function is responsible for placing the order and setting the status to pending_payment.
     *
     * @return int
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function placeOrder(): int
    {
        $this->quote = $this->checkoutSession->getQuote();
        if ($this->customerSession->isLoggedIn()) {
            $orderId = $this->cartManagement->placeOrder($this->quote->getId());
        } else {
            $this->quote->setCheckoutMethod($this->cartManagement::METHOD_GUEST);
            $this->quote->setCustomerEmail($this->customerSession->getEmail());
            $orderId = $this->cartManagement->placeOrder($this->quote->getId());
        }

        $order = $this->orderRepository->get($orderId);
        $comment = __("START_ORDER");

        $order->setState('pending_payment')->setStatus('pending_payment');
        $order->addCommentToStatusHistory($comment);
        $order->getPayment()->setMethod('iyzipay');

        $this->orderRepository->save($order);

        return $orderId;
    }

    /**
     * Get Locale
     *
     * This function is responsible for getting the locale.
     *
     * @param  int  $websiteId
     * @return string
     */
    private function getLocale(int $websiteId): string
    {
        return $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get Currency
     *
     * This function is responsible for getting the currency.
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getCurrency(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get Callback Url
     *
     * This function is responsible for getting the callback url.
     *
     * @throws NoSuchEntityException
     */
    private function getCallbackUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
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
    private function generateConversationId($quoteId): string
    {
        return $this->stringHelper->generateConversationId($quoteId);
    }

    /**
     * Get Customer Card User Key
     *
     * This function is responsible for getting the customer card user key.
     *
     * @param  int  $customerId
     * @param  string  $apiKey
     * @return string
     */
    private function getCustomerCardUserKey(int $customerId, string $apiKey): string
    {
        if ($customerId) {
            $iyziCardFind = $this->iyziCardFactory->create()->getCollection()
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
    private function storeSessionData($customerMail, $customerBasketId): void
    {
        $this->customerSession->setEmail($customerMail);
        $this->checkoutSession->setGuestQuoteId($customerBasketId);
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
     * @param $orderId
     * @param $callBack
     * @param $magentoVersion
     * @return mixed
     */
    private function createPaymentOption(
        $objectHelper,
        $checkoutSession,
        $customerCardUserKey,
        $locale,
        $conversationId,
        $currency,
        $quoteId,
        $orderId,
        $callBack,
        $magentoVersion
    ): mixed {
        $iyzico = $objectHelper->createPaymentOption($checkoutSession, $customerCardUserKey, $locale, $conversationId,
            $currency, $quoteId, $orderId, $callBack, $magentoVersion);
        $iyzico->buyer = $objectHelper->createBuyerObject($checkoutSession, $this->customerSession->getEmail());
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
     * @param $orderId
     * @return array
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function handleRequestResponse($requestResponse, $orderId): array
    {
        if (isset($requestResponse->status) && $requestResponse->status == 'success') {
            return $this->processSuccessfulResponse($requestResponse, $orderId);
        } elseif (isset($requestResponse->errorCode)) {
            return $this->processErrorResponse($requestResponse);
        } elseif ($requestResponse === null) {
            return $this->processNullResponse();
        }

        $this->errorLogger->critical("result must be an array.", ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        return [
            'success' => false,
            'message' => "Unknown Error",
            'code' => "0"
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
    private function processSuccessfulResponse($requestResponse, $orderId): array
    {
        $this->quote = $this->checkoutSession->getQuote();
        $quoteId = $this->quote->getId();

        $this->saveIyziOrderJobTable($requestResponse, $orderId, $quoteId);
        return ['success' => true, 'url' => $requestResponse->paymentPageUrl];
    }

    /**
     * Save Iyzi Order Table
     *
     * This function is responsible for saving the iyzi order table.
     *
     * @param $requestResponse
     * @param $orderId
     * @param $quoteId
     */
    private function saveIyziOrderJobTable($requestResponse, $orderId, $quoteId): void
    {
        $iyzicoOrderJob = $this->_objectManager->create('Iyzico\Iyzipay\Model\IyziOrderJob');

        $iyzicoOrderJob->setData([
            'order_id' => $orderId,
            'quote_id' => $quoteId,
            'iyzico_payment_token' => $requestResponse->token,
            'iyzico_conversation_id' => $requestResponse->conversationId,
            'status' => 'received'
        ]);

        try {
            $iyzicoOrderJob->save();
        } catch (Exception $e) {
            $this->errorLogger->critical($e->getMessage());
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
    private function processErrorResponse($requestResponse): array
    {
        $this->errorLogger->critical(
            "Error Code: ".$requestResponse->errorCode." Error Message: ".$requestResponse->errorMessage,
            ['fileName' => __FILE__, 'lineNumber' => __LINE__]
        );

        return [
            'success' => false,
            'message' => $requestResponse->errorMessage,
            'code' => $requestResponse->errorCode
        ];
    }

    /**
     * Process Null Response
     *
     * This function is responsible for processing the null response.
     *
     * @return array
     */
    private function processNullResponse(): array
    {
        $this->errorLogger->critical(
            "requestResponse must not be NULL",
            ['fileName' => __FILE__, 'lineNumber' => __LINE__]
        );

        return [
            'success' => false,
            'message' => "Unknown Error",
            'code' => "0"
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
    private function createJsonResult($result): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }

}
