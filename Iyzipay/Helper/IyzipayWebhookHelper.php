<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template\Context;

class IyzipayWebhookHelper
{
    /**
     * TODO: getWebhookUrl, getSecretKey, webhookHttpResponse (yeni: sendWebhookHttpResponse)
     */

    private ScopeConfigInterface $config;

    public function __construct(Context $context)
    {
        $this->config = $context->getScopeConfig();
    }

    public function getWebhookUrl(): mixed
    {
        return $this->config->getValue('payment/iyzipay/webhook_url_key', $this->getScopeInterface());
    }

    public function getSecretKey(): mixed
    {
        return $this->config->getValue('payment/iyzipay/secret_key', $this->getScopeInterface());
    }

    public function sendWebhookHttpResponse($message, $status)
    {
        $httpMessage = array('message' => $message, 'status' => $status);
        header('Content-Type: application/json, Status: ' . $status, true, $status);
        echo json_encode($httpMessage);
        exit();
    }
}
