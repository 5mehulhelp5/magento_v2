<?php

namespace Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest;

use Iyzico\Iyzipay\Logger\IyziWebhookLogger;

use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\App\Config\ScopeConfigInterface;


/**
 * Class Request
 *
 * @package Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest
 */
class Request
{
    protected $logger;
    protected $scopeConfig;

    /**
     * Request constructor.
     *
     * @param  IyziWebhookLogger  $logger
     * @param  ScopeConfigInterface $scopeConfig;
     */
    public function __construct(IyziWebhookLogger $logger, ScopeConfigInterface $scopeConfig)
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param  RestRequest  $subject
     * @param  array    $result
     * @return array|string[]
     */
    public function afterGetAcceptTypes(RestRequest $subject, array $result): array
    {

        $webhookUrlKey = $this->scopeConfig->getValue('payment/iyzipay/webhook_url_key');

        if ($subject->getRequestUri() === ('/rest/V1/iyzico/webhook/' . $webhookUrlKey) || $subject->getRequestUri() === '/index.php/rest/V1/iyzico/callback/') {
            $result = ['text/html'];
        }

        return $result;
    }
}
