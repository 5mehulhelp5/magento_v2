<?php

namespace Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest;

use Iyzico\Iyzipay\Logger\IyziWebhookLogger;
use Magento\Framework\Webapi\Rest\Request as RestRequest;

/**
 * Class Request
 *
 * @package Iyzico\Iyzipay\Plugin\Magento\Framework\Webapi\Rest
 */
class Request
{
    protected $logger;

    /**
     * Request constructor.
     *
     * @param  IyziWebhookLogger  $logger
     */
    public function __construct(IyziWebhookLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param  RestRequest  $subject
     * @param  array    $result
     * @return array|string[]
     */
    public function afterGetAcceptTypes(RestRequest $subject, array $result): array
    {
        $this->logger->info('requst.php', [
            'subject' => $subject,
            'result' => $result
        ]);

        if ($subject->getRequestUri() === '/rest/v1/iyzico/webhook/' || $subject->getRequestUri() === '/index.php/rest/V1/iyzico/callback/') {
            $result = ['text/html'];

            $this->logger->info('if çalıştı: ', [
                'subject' => $subject->getRequestUri(),
                'result' => $result
            ]);
        }

        return $result;
    }
}
