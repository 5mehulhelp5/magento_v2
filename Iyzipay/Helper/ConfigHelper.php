<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigHelper
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get Secret Key
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getSecretKey(): mixed
    {
        return $this->scopeConfig->getValue(
            'payment/iyzipay/secret_key',
            $this->getScopeInterface(),
            $this->getWebsiteId()
        );
    }

    /**
     * Get Scope Type
     *
     * @return string
     */
    public function getScopeInterface(): string
    {
        return ScopeInterface::SCOPE_WEBSITES;
    }

    /**
     * Magento Website Id
     *
     * @return int
     * @throws LocalizedException
     */
    public function getWebsiteId(): int
    {
        return $this->storeManager->getWebsite()->getId();
    }

    /**
     * Get Webhook Url Key
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getWebhookUrlKey(): mixed
    {
        return $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key',
            $this->getScopeInterface(),
            $this->getWebsiteId()
        );
    }
}