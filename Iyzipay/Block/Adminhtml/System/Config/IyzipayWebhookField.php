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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Magento\Backend\Block\Template\Context;
/**
 * Class GetWebhookUrlField
 *
 * @package Vendor\Module\Block\Adminhtml\Config
 * @extends Field
 *
 * This class is used in etc/adminhtml/system.xml
 */
class IyzipayWebhookField extends Field
{
    protected StoreManagerInterface $storeManager;
    protected IyziErrorLogger $logger;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        IyziErrorLogger $logger,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    private function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Retrieve the webhook URL and submit button HTML
     *
     * @param AbstractElement $element
     * @return string
     * @throws NoSuchEntityException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->logger->info("Store ID: " . $this->getStoreId());
        $this->logger->info("ScopeInterface::SCOPE_STORE: " . ScopeInterface::SCOPE_STORE);

        $webhookUrlKey = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key', ScopeInterface::SCOPE_STORE, $this->getStoreId());

        $this->logger->info("Webhook URL Key: " . $webhookUrlKey);

        if ($webhookUrlKey) {
            return $this->_storeManager->getStore()->getBaseUrl() . 'rest/V1/iyzico/webhook/' . $webhookUrlKey . '<br>' . $this->getWebhookSubmitButtonHtml();
        } else {
            $objectManager = ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('core_config_data');
            $query = "SELECT value FROM " . $tableName . " WHERE path = 'payment/iyzipay/webhook_url_key' AND scope = 'stores' AND scope_id = " . $this->getStoreId();
            $result = $connection->fetchOne($query);

            if ($result) {
                $this->logger->info("Webhook URL Key from DB: " . $result);
                return $this->_storeManager->getStore()->getBaseUrl() . 'rest/V1/iyzico/webhook/' . $result . '<br>' . $this->getWebhookSubmitButtonHtml();
            } else {
                return 'Clear cookies and then push the "Save Config" button';
            }
        }
    }

    /**
     * Generate webhook submit button HTML
     *
     * @return string
     */
    public function getWebhookSubmitButtonHtml(): string
    {

        $this->logger->info("Store ID: " . $this->getStoreId());
        $this->logger->info("ScopeInterface::SCOPE_STORE: " . ScopeInterface::SCOPE_STORE);

        $isWebhookButtonActive = $this->_scopeConfig->getValue('payment/iyzipay/webhook_url_key_active', ScopeInterface::SCOPE_STORE, $this->getStoreId());

        $this->logger->info("isWebhookButtonActive: " . $isWebhookButtonActive);

        if ($isWebhookButtonActive == 2) {
            $htmlButton = '<form action="#" method="post">
                           <button class="btn btn-light" type="submit" name="button">Activate</button>
                           <a href="mailto:integration@vendor.com">integration@vendor.com</a>
                           </form>';

            $postData = $this->getRequest()->getPost();

            $this->logger->info("Post Data: " . json_encode($postData));

            if ($postData) {
                $this->deactivateWebhookButton();
            }

            return $htmlButton;
        }
        return '';
    }

    /**
     * Deactivate the webhook button
     */
    protected function deactivateWebhookButton()
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('core_config_data');
        $sql = "UPDATE " . $tableName . " SET value = '0' WHERE path = 'payment/iyzipay/webhook_url_key_active'";
        $connection->query($sql);
    }
}
