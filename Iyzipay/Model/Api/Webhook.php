<?php

namespace Iyzico\Iyzipay\Model\Api;

use Exception;
use Iyzico\Iyzipay\Controller\Response\IyzicoCheckoutForm;
use Iyzico\Iyzipay\Helper\WebhookHelper;
use Iyzico\Iyzipay\Infrastructure\Contracts\WebhookInterface;
use Iyzico\Iyzipay\Logger\IyziLogger;
use stdClass;

/**
 * Webhook Class
 * This class is responsible for handling the webhook response from Iyzico.
 *
 * @package Iyzico\Iyzipay\Model\Api
 * @see WebhookInterface
 */
class Webhook implements WebhookInterface
{
    private WebhookHelper $webhookHelper;
    private IyzicoCheckoutForm $checkoutForm;
    private IyziLogger $logger;

    /**
     * Constructor
     * Constructs the Webhook Class
     *
     * @param WebhookHelper $webhookHelper
     * @param IyzicoCheckoutForm $checkoutForm
     * @param IyziLogger $logger
     */
    public function __construct(WebhookHelper $webhookHelper, IyzicoCheckoutForm $checkoutForm, IyziLogger $logger)
    {
        $this->webhookHelper = $webhookHelper;
        $this->checkoutForm = $checkoutForm;
        $this->logger = $logger;
    }

    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     * @return string The response from the webhook.
     */
    public function getResponse(string $webhookUrlKey): string
    {
        $expectedWebhookUrlKey = $this->webhookHelper->getWebhookUrl();

        if ($webhookUrlKey != $expectedWebhookUrlKey) {
            $this->logger->error("Error: '{$webhookUrlKey}' is not a valid webhook URL key. Expected: '{$expectedWebhookUrlKey}'.");
            return $this->webhookHelper->webhookHttpResponse("Error: Webhook URL Key", 404);
        }

        try {
            $body = file_get_contents('php://input');
            $response = json_decode($body);
        } catch (Exception $e) {
            $this->logger->error("Error: {$e->message()}.");
            return $this->webhookHelper->webhookHttpResponse("Error: {$e->message()}", 400);
        }

        if (isset($response->iyziEventType) && isset($response->token) && isset($response->paymentConversationId)) {
            $signature = base64_encode(sha1($this->webhookHelper->getSecretKey() . $response->iyziEventType . $response->token, true));
            if ($signature) {
                return $this->getHttpResponse($response);
            } else {
                $this->logger->error("Error: X-IYZ-SIGNATURE is not valid.");
                return $this->webhookHelper->webhookHttpResponse("Error: X-IYZ-SIGNATURE is not valid.", 404);
            }
        } else {
            $this->logger->error("Error: Invalid parameters provided.");
            return $this->webhookHelper->webhookHttpResponse("Error: Invalid parameters provided.", 404);
        }
    }

    /**
     * Get the HTTP response based on the webhook response.
     *
     * @param stdClass $response The webhook response object.
     * @return string The HTTP response.
     */
    public function getHttpResponse(stdClass $response): string
    {
        if (!isset($response->iyziEventType) || !isset($response->token) || !isset($response->paymentConversationId)) {
            $this->logger->error("Error: iyziEventType, token, paymentConversationId not found in the response");
            return $this->webhookHelper->webhookHttpResponse("Error: iyziEventType, token, paymentConversationId not found in the response", 404);
        }
        return $this->checkoutForm->iyzicoResponse("webhook", $response->paymentConversationId, $response->token, $response->iyziEventType);
    }
}
