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

namespace Iyzico\Iyzipay\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;

class IyzipayWebhookField extends Field
{
    protected $storeManager;
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        IyziErrorLogger $logger,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    protected function getStoreId()
    {
        try {
            return $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $webhookUrlKey = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );

        $this->logger->info('Webhook URL Key: ' . $webhookUrlKey);

        if ($webhookUrlKey) {
            $baseUrl = $this->storeManager->getStore($this->getStoreId())->getBaseUrl();
            return $baseUrl . 'rest/V1/iyzico/webhook/' . $webhookUrlKey . '<br>' . $this->getWebhookSubmitButtonHtml();
        } else {
            return 'Clear cookies and then push the "Save Config" button';
        }
    }

    public function getWebhookSubmitButtonHtml(): string
    {
        $isWebhookButtonActive = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key_active',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );

        if ($isWebhookButtonActive == 2) {
            $htmlButton = '<form action="#" method="post">
                           <button class="btn btn-light" type="submit" name="button">Activate</button>
                           <a href="mailto:integration@iyzico.com">integration@iyzico.com</a>
                           </form>';

            $postData = $this->getRequest()->getPost();

            if ($postData) {
                $this->deactivateWebhookButton();
            }

            return $htmlButton;
        }
        return '';
    }

    protected function deactivateWebhookButton()
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('core_config_data');
        $storeId = $this->getStoreId();
        $sql = "UPDATE " . $tableName . " SET value = '0' WHERE path = 'payment/iyzipay/webhook_url_key_active' AND scope = 'stores' AND scope_id = " . $storeId;
        $connection->query($sql);
    }
}
