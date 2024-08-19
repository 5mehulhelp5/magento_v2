<?php

namespace Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest;

use Iyzico\Iyzipay\Logger\IyziWebhookLogger;

use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;


/**
 * Class Request
 *
 * @package Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest
 */
class Request
{
    protected $logger;
    protected $scopeConfig;
    protected $websiteId;

    /**
     * Request constructor.
     *
     * @param  IyziWebhookLogger  $logger
     * @param  ScopeConfigInterface $scopeConfig;
     */
    public function __construct(
        IyziWebhookLogger $logger,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->websiteId = $storeManager->getWebsite()->getId();

    }

    /**
     * @param  RestRequest  $subject
     * @param  array    $result
     * @return array|string[]
     */
    public function afterGetAcceptTypes(RestRequest $subject, array $result): array
    {
        $webhookUrlKey = $this->scopeConfig->getValue(
            'payment/iyzipay/webhook_url_key',
            ScopeInterface::SCOPE_WEBSITES,
            $this->websiteId
        );

        $this->logger->info('Request.php Webhook URL Key: ' . $webhookUrlKey ?? 'null');

        if ($subject->getRequestUri() === ('/rest/V1/iyzico/webhook/' . $webhookUrlKey) || $subject->getRequestUri() === '/index.php/rest/V1/iyzico/callback/') {
            $result = ['text/html'];
        }

        return $result;
    }
}
