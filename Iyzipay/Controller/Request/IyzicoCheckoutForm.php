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

use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoFormObjectGenerator;
use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoPkiStringBuilder;
use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoRequest;
use Iyzico\Iyzipay\Helper\ObjectHelper;
use Iyzico\Iyzipay\Helper\PriceHelper;
use Iyzico\Iyzipay\Helper\StringHelper;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;


class IyzicoCheckoutForm extends Action
{

    protected $_context;
    protected $_pageFactory;
    protected $_jsonEncoder;
    protected $_checkoutSession;
    protected $_customerSession;
    protected $_scopeConfig;
    protected $_iyziCardFactory;
    protected $_storeManager;
    protected $_stringHelper;
    protected $_priceHelper;

    public function __construct(
        Context                         $context,
        EncoderInterface                $encoder,
        PageFactory                     $pageFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        Session                         $customerSession,
        ScopeConfigInterface            $scopeConfig,
        IyziCardFactory                 $iyziCardFactory,
        StoreManagerInterface           $storeManager,
        StringHelper                    $stringHelper,
        PriceHelper                     $priceHelper
    )
    {

        $this->_context = $context;
        $this->_pageFactory = $pageFactory;
        $this->_jsonEncoder = $encoder;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_scopeConfig = $scopeConfig;
        $this->_iyziCardFactory = $iyziCardFactory;
        $this->_storeManager = $storeManager;
        $this->_stringHelper = $stringHelper;
        $this->_priceHelper = $priceHelper;
        parent::__construct($context);
    }

    /**
     * Takes the place of the M1 indexAction.
     * Now, every IyziPayGeneratorCheckout has an execute
     *
     ***/
    public function execute()
    {

        /* customer to checkout session */

        $postData = $this->getRequest()->getPostValue();
        $checkoutSession = $this->_checkoutSession->getQuote();

        $storeId = $this->_storeManager->getStore()->getId();
        $locale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        $currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        $callBack = $this->_storeManager->getStore()->getBaseUrl();
        $cardId = $checkoutSession->getId();

        /* Get Version */
        $objectManager = ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $magentoVersion = $productMetadata->getVersion();

        $this->checkAndSetCookieSameSite();

        $rand = uniqid();
        $customerId = 0;

        if ($this->_customerSession->isLoggedIn())
            $customerId = $this->_customerSession->getCustomerId();

        $apiKey = $this->_scopeConfig->getValue('payment/iyzipay/api_key');
        $secretKey = $this->_scopeConfig->getValue('payment/iyzipay/secret_key');
        $sandboxStatus = $this->_scopeConfig->getValue('payment/iyzipay/sandbox');

        $baseUrl = 'https://api.iyzipay.com';
        if ($sandboxStatus)
            $baseUrl = 'https://sandbox-api.iyzipay.com';

        if ($customerId) {

            $iyziCardFind = $this->_iyziCardFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('api_key', $apiKey)
                ->addFieldToSelect('card_user_key');

            $iyziCardFind = $iyziCardFind->getData();

            $customerCardUserKey = !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : '';

        } else {

            $customerCardUserKey = '';
        }

        $iyzicoFormObject = new IyzicoFormObjectGenerator();

        $objectHelper = new ObjectHelper($this->_stringHelper, $this->_priceHelper);

        $iyzicoPkiStringBuilder = new IyzicoPkiStringBuilder();
        $iyzicoRequest = new IyzicoRequest();

        $guestEmail = false;
        if (isset($postData['iyziQuoteEmail']) && isset($postData['iyziQuoteId'])) {

            $this->_customerSession->setEmail($postData['iyziQuoteEmail']);
            $this->_checkoutSession->setGuestQuoteId($postData['iyziQuoteId']);
            $guestEmail = $postData['iyziQuoteEmail'];
        }

        $iyzico = $objectHelper->createPaymentOption($checkoutSession, $customerCardUserKey, $locale, $currency, $cardId, $callBack, $magentoVersion);

        $iyzico->buyer = $iyzicoFormObject->generateBuyer($checkoutSession, $guestEmail);
        $iyzico->billingAddress = $iyzicoFormObject->generateBillingAddress($checkoutSession);
        $iyzico->shippingAddress = $iyzicoFormObject->generateShippingAddress($checkoutSession);
        $iyzico->basketItems = $iyzicoFormObject->generateBasketItems($checkoutSession);

        $orderObject = $iyzicoPkiStringBuilder->createFormObjectSort($iyzico);
        $iyzicoPkiString = $iyzicoPkiStringBuilder->pkiStringGenerate($orderObject);
        $authorization = $iyzicoPkiStringBuilder->authorizationGenerate($iyzicoPkiString, $apiKey, $secretKey, $rand);

        $iyzicoJson = json_encode($iyzico, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $requestResponse = $iyzicoRequest->iyzicoCheckoutFormRequest($baseUrl, $iyzicoJson, $authorization);


        $result = false;

        if ($requestResponse->status == 'success') {

            $this->_customerSession->setIyziToken($requestResponse->token);
            $result = $requestResponse->paymentPageUrl;

        } else {

            $result = $requestResponse->errorMessage;
        }

        $this->getResponse()->representJson($result);
        return;

    }

    private function checkAndSetCookieSameSite()
    {

        $checkCookieNames = array('PHPSESSID', 'OCSESSID', 'default', 'PrestaShop-', 'wp_woocommerce_session_');

        foreach ($_COOKIE as $cookieName => $value) {
            foreach ($checkCookieNames as $checkCookieName) {
                if (stripos($cookieName, $checkCookieName) === 0) {
                    $this->setcookieSameSite($cookieName, $_COOKIE[$cookieName], time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);
                }
            }
        }
    }

    private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly)
    {

        if (PHP_VERSION_ID < 70300) {

            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly
            ]);


        }
    }

}
