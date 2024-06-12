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
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\CartManagementInterface;


class IyzicoCheckoutForm extends Action
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

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        IyziCardFactory $iyziCardFactory,
        StringHelper $stringHelper,
        PriceHelper $priceHelper,
        JsonFactory $resultJsonFactory,
        Quote $quote,
        CartManagementInterface $cartManagement,
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
        $this->_iyziLogger = $iyziLogger;
        parent::__construct($context);
    }

    public function execute()
    {

        $defination = [
            'rand' => uniqid(),
            'customerId' => 0,
            'customerCardUserKey' => '',
            'baseUrl' => $this->_scopeConfig->getValue('payment/iyzipay/sandbox') ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->_scopeConfig->getValue('payment/iyzipay/api_key'),
            'secretKey' => $this->_scopeConfig->getValue('payment/iyzipay/secret_key'),
        ];

        # Object Helper
        $objectHelper = new ObjectHelper($this->_stringHelper, $this->_priceHelper);

        # Pki String Builder
        $pkiStringBuilder = new PkiStringBuilder();

        # Request Helper
        $requestHelper = new RequestHelper();

        # Cookie Helper
        $cookieHelper = new CookieHelper();

        # Request Data
        $postData = $this->getRequest()->getPostValue();

        # Request Mail Data
        $customerMail = $postData['iyziQuoteEmail'];

        # Request BasketId Data
        $customerBasketId = $postData['iyziQuoteId'];

        # Checkout Session
        $checkoutSession = $this->_checkoutSession->getQuote();

        # StoreId
        $storeId = $this->_storeManager->getStore()->getId();

        # Store Name
        $storeName = $this->_storeManager->getStore()->getName();

        # Locale Code
        $locale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);

        # Currency Code
        $currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

        # Call BackUrl
        $callBack = $this->_storeManager->getStore()->getBaseUrl();

        # Cart Id
        $cartId = $checkoutSession->getId();

        # Object Manager
        $objectManager = ObjectManager::getInstance();

        # Product Meta Data
        $productMetaData = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

        # Magento Version
        $magentoVersion = $productMetaData->getVersion();

        # Cookie SameSite
        $cookieHelper->ensureCookiesSameSite();

        # Conversation Id
        $conversationId = $this->_stringHelper->generateConversationId($storeName, $cartId);

        if ($this->_customerSession->isLoggedIn()) {
            $defination['customerId'] = $this->_customerSession->getCustomerId();
        }

        if ($defination['customerId']) {
            $iyziCardFind = $this->_iyziCardFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $defination['customerId'])
                ->addFieldToFilter('api_key', $defination['apiKey'])
                ->addFieldToSelect('card_user_key');

            $iyziCardFind = $iyziCardFind->getData();
            $defination['customerCardUserKey'] = !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : '';
        }

        if (isset($customerMail) && isset($customerBasketId)) {
            $this->_customerSession->setEmail($customerMail);
            $this->_checkoutSession->setGuestQuoteId($customerBasketId);
        }

        $iyzico = $objectHelper->createPaymentOption($checkoutSession, $defination['customerCardUserKey'], $locale, $conversationId, $currency, $cartId, $callBack, $magentoVersion);

        $iyzico->buyer = $objectHelper->createBuyerObject($checkoutSession, $customerMail);
        $iyzico->billingAddress = $objectHelper->createBillingAddressObject($checkoutSession);
        $iyzico->shippingAddress = $objectHelper->createShippingAddressObject($checkoutSession);
        $iyzico->basketItems = $objectHelper->createBasketItems($checkoutSession);

        $orderObject = $pkiStringBuilder->sortFormObject($iyzico);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($orderObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);

        $iyzicoJson = json_encode($iyzico, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestResponse = $requestHelper->sendCheckoutFormRequest($defination['baseUrl'], $iyzicoJson, $authorization);


        if (isset($requestResponse->status) && $requestResponse->status == 'success') {
            $this->processSuccessfulResponse($requestResponse, $currency);
        } elseif (isset($requestResponse->errorCode)) {
            $result = $this->processErrorResponse($requestResponse);
        } elseif ($requestResponse === null) {
            $result = $this->processNullResponse();
        }

        $resultJson = $this->_resultJsonFactory->create();
        return $resultJson->setData($result);

    }

    private function processSuccessfulResponse($requestResponse, $currency)
    {
        $this->_quote = $this->_checkoutSession->getQuote();
        $this->_quote->setIyziCurrency($currency);

        $magentoOrderId = $this->placeOrder();

        $this->createIyziOrderJob($requestResponse, $magentoOrderId);

        $this->_customerSession->setIyziToken($requestResponse->token);
        return ['success' => true, 'url' => $requestResponse->paymentPageUrl];
    }

    private function placeOrder()
    {
        if ($this->_customerSession->isLoggedIn()) {
            return $this->_cartManagement->placeOrder($this->_quote->getId());
        }

        $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
        $this->_quote->setCustomerEmail($this->_customerSession->getEmail());
        return $this->_cartManagement->placeOrder($this->_quote->getId());
    }

    private function createIyziOrderJob($requestResponse, $magentoOrderId)
    {
        $iyzicoOrderJob = $this->_objectManager->create('Iyzico\Iyzipay\Model\IyziOrderJob');

        $iyzicoOrderJob->setData([
            'magento_order_id' => $magentoOrderId,
            'iyzico_payment_token' => $requestResponse->token,
            'iyzico_conversationId' => $requestResponse->conversationId,
            'expire_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        try {
            $iyzicoOrderJob->save();
        } catch (\Exception $e) {
            $this->_iyziLogger->critical($e->getMessage());
        }
    }

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



}
