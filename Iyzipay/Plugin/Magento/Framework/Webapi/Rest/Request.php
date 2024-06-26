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

        $this->logger->info('request.php if öncesi: ', [
            'result' => $result,
        ]);


        if ($subject->getRequestUri() === ('/rest/v1/iyzico/webhook/' . $webhookUrlKey) || $subject->getRequestUri() === '/index.php/rest/V1/iyzico/callback/') {
            $result = ['text/html'];
        }

        $this->logger->info('request.php if sonrası: ', [
            'result' => $result,
        ]);

        return $result;
    }
}
