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
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;

use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoPkiStringBuilder;
use Iyzico\Iyzipay\Controller\IyzicoBase\IyzicoRequest;
use Iyzico\Iyzipay\Helper\IyzicoHelper;
use stdClass;


class IyzipayConfigSaveBefore implements ObserverInterface
{

    protected $scopeConfig;
    protected $storeManager;
    protected $iyzicoHelper;
    protected $configWriter;
    protected $request;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        IyzicoHelper $iyzicoHelper,
        WriterInterface $configWriter,
        Http $request
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->iyzicoHelper = $iyzicoHelper;
        $this->configWriter = $configWriter;
        $this->request = $request;
    }

    public function execute(EventObserver $observer)
    {
        $this->webhookUrlKey();
        $this->webhookSetControll();

        $postData = $this->request->getPostValue();
        $this->initSetWebhookUrlKey($postData);

        if (!empty($postData['groups']['iyzipay']['fields']['active'])) {

            $apiKey = $postData['groups']['iyzipay']['fields']['api_key']['value'];
            $secretKey = $postData['groups']['iyzipay']['fields']['secret_key']['value'];
            $randNumer = rand(100000, 99999999);

            $websiteId = $this->storeManager->getWebsite()->getId();
            $locale = $this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );

            $overlayObject = new stdClass();
            $overlayObject->locale = $this->iyzicoHelper->cutLocale($locale);
            $overlayObject->conversationId = $randNumer;
            $overlayObject->position = $postData['groups']['iyzipay']['fields']['overlayscript']['value'];

            $iyzicoPkiStringBuilder = new IyzicoPkiStringBuilder();
            $iyzicoRequest = new IyzicoRequest();

            $pkiString = $iyzicoPkiStringBuilder->pkiStringGenerate($overlayObject);
            $authorization = $iyzicoPkiStringBuilder->authorizationGenerate($pkiString, $apiKey, $secretKey, $randNumer);

            $iyzicoJson = json_encode($overlayObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $requestResponse = $iyzicoRequest->iyzicoOverlayScriptRequest($iyzicoJson, $authorization);

            if ($requestResponse->status == 'success') {

                $this->configWriter->save(
                    'payment/iyzipay/protectedShopId',
                    $requestResponse->protectedShopId,
                    ScopeInterface::SCOPE_WEBSITES,
                    $websiteId
                );

            }

        }

    }

    public function initSetWebhookUrlKey($postData)
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $webhookActive = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key_active',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        if ($webhookActive == 0) {
            $apiKey = $postData['groups']['iyzipay']['fields']['api_key']['value'];
            $secretKey = $postData['groups']['iyzipay']['fields']['secret_key']['value'];
            if (isset($apiKey) && isset($secretKey)) {
                $randNumer = rand(100000, 99999999);
                $sandboxStatus = $this->scopeConfig->getValue(
                    'payment/iyzipay/sandbox',
                    ScopeInterface::SCOPE_WEBSITES,
                    $websiteId
                );
                $baseUrl = 'https://api.iyzipay.com';

                if ($sandboxStatus)
                    $baseUrl = 'https://sandbox-api.iyzipay.com';


                $webhook_url_key = $this->scopeConfig->getValue(
                    'payment/iyzipay/webhook_url_key',
                    ScopeInterface::SCOPE_WEBSITES,
                    $websiteId
                );

                $setWebhookUrl = new stdClass();
                $setWebhookUrl->webhookUrl = $this->storeManager->getStore()->getBaseUrl() . 'rest/V1/iyzico/webhook/' . $webhook_url_key;

                $iyzicoPkiStringBuilder = new IyzicoPkiStringBuilder();
                $iyzicoRequest = new IyzicoRequest();

                $pkiString = $iyzicoPkiStringBuilder->pkiStringGenerate($setWebhookUrl);
                $authorization = $iyzicoPkiStringBuilder->authorizationGenerate($pkiString, $apiKey, $secretKey, $randNumer);

                $iyzicoJson = json_encode($setWebhookUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $requestResponseWebhook = $iyzicoRequest->iyzicoPostWebhookUrlKey($baseUrl, $iyzicoJson, $authorization);
                $requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED';
                if ($requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED' || $requestResponseWebhook->merchantNotificationUpdateStatus == 'CREATED') {
                    $this->configWriter->save(
                        'payment/iyzipay/webhook_url_key_active',
                        '1',
                        ScopeInterface::SCOPE_WEBSITES,
                        $websiteId
                    );

                } else {
                    return $this->configWriter->save(
                        'payment/iyzipay/webhook_url_key_active',
                        '2',
                        ScopeInterface::SCOPE_WEBSITES,
                        $websiteId
                    );

                }
            }

        }

    }


    public function webhookSetControll()
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $webhookActive = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key_active',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        if (!$webhookActive) {
            $this->configWriter->save(
                'payment/iyzipay/webhook_url_key_active',
                '0',
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
        }
    }


    public function webhookUrlKey()
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $webhookUrlKey = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        if (!$webhookUrlKey) {
            $webhookUrlKeyUniq = substr(base64_encode(time() . mt_rand()), 15, 6);
            $this->configWriter->save(
                'payment/iyzipay/webhook_url_key',
                $webhookUrlKeyUniq,
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );

        }
    }


}
