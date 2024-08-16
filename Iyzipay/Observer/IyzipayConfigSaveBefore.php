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

namespace Iyzico\Iyzipay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Request\Http;

use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoPkiStringBuilder;
use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoRequest;
use Iyzico\Iyzipay\Helper\IyzicoHelper;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use stdClass;

class IyzipayConfigSaveBefore implements ObserverInterface
{

    protected $_scopeConfig;
    protected $_storeManager;
    protected $_iyzicoHelper;
    protected $_configWriter;
    protected $_request;
    protected $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        Http $request,
        IyzicoHelper $iyzicoHelper,
        IyziErrorLogger $logger
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_configWriter = $configWriter;
        $this->_request = $request;
        $this->_iyzicoHelper = $iyzicoHelper;
        $this->logger = $logger;
    }

    public function execute(EventObserver $observer)
    {


        $postData = $this->_request->getPostValue();
        $this->webhookUrlKey();
        $this->webhookSetControll();

        $this->logger->info("IyzipayConfigSaveBefore.php Post Data: " . json_encode($postData));

        $this->initSetWebhookUrlKey($postData);

        if (!empty($postData['groups']['iyzipay']['fields']['active'])) {


            $apiKey = $postData['groups']['iyzipay']['fields']['api_key']['value'];
            $secretKey = $postData['groups']['iyzipay']['fields']['secret_key']['value'];
            $randNumer = rand(100000, 99999999);

            $storeId = $this->_storeManager->getStore()->getId();

            $this->logger->info("IyzipayConfigSaveBefore.php Store ID: " . $storeId);

            $locale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);

            $this->logger->info("IyzipayConfigSaveBefore.php Locale: " . $locale);

            $overlayObject = new stdClass();
            $overlayObject->locale = $this->_iyzicoHelper->cutLocale($locale);
            $overlayObject->conversationId = $randNumer;
            $overlayObject->position = $postData['groups']['iyzipay']['fields']['overlayscript']['value'];

            $iyzicoPkiStringBuilder = new IyzicoPkiStringBuilder();
            $iyzicoRequest = new IyzicoRequest();

            $pkiString = $iyzicoPkiStringBuilder->pkiStringGenerate($overlayObject);
            $authorization = $iyzicoPkiStringBuilder->authorizationGenerate($pkiString, $apiKey, $secretKey, $randNumer);

            $iyzicoJson = json_encode($overlayObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $requestResponse = $iyzicoRequest->iyzicoOverlayScriptRequest($iyzicoJson, $authorization);

            if ($requestResponse->status == 'success') {

                $this->_configWriter->save('payment/iyzipay/protectedShopId', $requestResponse->protectedShopId, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);

            }

        }

    }

    public function initSetWebhookUrlKey($postData)
    {
        $storeId = $this->_storeManager->getStore()->getId();

        $this->logger->info("IyzipayConfigSaveBefore.php initSetWebhookUrlKey Store ID: " . $storeId);

        $webhookActive = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key_active', ScopeInterface::SCOPE_STORE, $storeId);

        $this->logger->info("IyzipayConfigSaveBefore.php initSetWebhookUrlKey Webhook Active: " . $webhookActive);

        if ($webhookActive == 0) {
            $apiKey = $postData['groups']['iyzipay']['fields']['api_key']['value'];
            $secretKey = $postData['groups']['iyzipay']['fields']['secret_key']['value'];
            if (isset($apiKey) && isset($secretKey)) {
                $randNumer = rand(100000, 99999999);
                $sandboxStatus = $this->_scopeConfig->getValue('payment/iyzipay/sandbox', ScopeInterface::SCOPE_STORE, $storeId);
                $baseUrl = 'https://api.iyzipay.com';

                if ($sandboxStatus)
                    $baseUrl = 'https://sandbox-api.iyzipay.com';


                $webhook_url_key = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key', ScopeInterface::SCOPE_STORE, $storeId);

                $this->logger->info("IyzipayConfigSaveBefore.php initSetWebhookUrlKey Webhook URL Key: " . $webhook_url_key);

                $setWebhookUrl = new stdClass();
                $setWebhookUrl->webhookUrl = $this->_storeManager->getStore()->getBaseUrl() . 'rest/V1/iyzico/webhook/' . $webhook_url_key;

                $this->logger->info("IyzipayConfigSaveBefore.php initSetWebhookUrlKey Webhook URL: " . $setWebhookUrl->webhookUrl);

                $iyzicoPkiStringBuilder = new IyzicoPkiStringBuilder();
                $iyzicoRequest = new IyzicoRequest();

                $pkiString = $iyzicoPkiStringBuilder->pkiStringGenerate($setWebhookUrl);
                $authorization = $iyzicoPkiStringBuilder->authorizationGenerate($pkiString, $apiKey, $secretKey, $randNumer);

                $iyzicoJson = json_encode($setWebhookUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $requestResponseWebhook = $iyzicoRequest->iyzicoPostWebhookUrlKey($baseUrl, $iyzicoJson, $authorization);
                $requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED';
                if ($requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED' || $requestResponseWebhook->merchantNotificationUpdateStatus == 'CREATED') {
                    $this->_configWriter->save('payment/iyzipay/webhook_url_key_active', '1', ScopeInterface::SCOPE_STORE, $storeId);

                } else {
                    return $this->_configWriter->save('payment/iyzipay/webhook_url_key_active', '2', ScopeInterface::SCOPE_STORE, $storeId);

                }
            }

        }

    }


    public function webhookSetControll()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $this->logger->info("IyzipayConfigSaveBefore.php webhookSetControll Store ID: " . $storeId);
        $webhookActive = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key_active', ScopeInterface::SCOPE_STORE, $storeId);
        $this->logger->info("IyzipayConfigSaveBefore.php webhookSetControll Webhook Active: " . $webhookActive);
        if (!$webhookActive) {
            $this->_configWriter->save('payment/iyzipay/webhook_url_key_active', '0', ScopeInterface::SCOPE_STORE, $storeId);
        }
    }


    public function webhookUrlKey()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $this->logger->info("IyzipayConfigSaveBefore.php webhookUrlKey Store ID: " . $storeId);
        $webhookUrlKey = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key', ScopeInterface::SCOPE_STORE, $storeId);
        $this->logger->info("IyzipayConfigSaveBefore.php webhookUrlKey Webhook URL Key: " . $webhookUrlKey);
        if (!$webhookUrlKey) {
            $webhookUrlKeyUniq = substr(base64_encode(time() . mt_rand()), 15, 6);
            $this->logger->info("IyzipayConfigSaveBefore.php webhookUrlKey Webhook URL Key Uniq: " . $webhookUrlKeyUniq);
            $this->_configWriter->save('payment/iyzipay/webhook_url_key', $webhookUrlKeyUniq, ScopeInterface::SCOPE_STORE, $storeId);
        }
    }


}
