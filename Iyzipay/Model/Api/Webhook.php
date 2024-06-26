<?php

namespace Iyzico\Iyzipay\Model\Api;

use Iyzico\Iyzipay\Controller\Response\IyzipayResponse;
use Iyzico\Iyzipay\Helper\WebhookHelper;
use Iyzico\Iyzipay\Api\WebhookInterface;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Iyzico\Iyzipay\Logger\IyziWebhookLogger;
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
    private IyzipayResponse $iyzipayResponse;
    private IyziErrorLogger $logger;
    private IyziWebhookLogger $webhookLogger;

    /**
     * Constructor
     * Constructs the Webhook Class
     *
     * @param WebhookHelper $webhookHelper
     * @param IyzipayResponse $iyzipayResponse
     * @param IyziErrorLogger $logger
     */
    public function __construct(WebhookHelper $webhookHelper, IyzipayResponse $iyzipayResponse, IyziErrorLogger $logger, IyziWebhookLogger $webhookLogger)
    {
        $this->webhookHelper = $webhookHelper;
        $this->iyzipayResponse = $iyzipayResponse;
        $this->logger = $logger;
        $this->webhookLogger = $webhookLogger;
    }

    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     */
    public function getResponse($webhookUrlKey)
    {
        $expectedWebhookUrlKey = $this->webhookHelper->getWebhookUrl();

        $this->webhookLogger->info("getResponse çalıştı", [
            'webhookUrlKey' => $webhookUrlKey,
            'expectedWebhookUrlKey' => $expectedWebhookUrlKey
        ]);

        if ($webhookUrlKey != $expectedWebhookUrlKey) {
            $this->logger->error("Error: '{$webhookUrlKey}' is not a valid webhook URL key. Expected: '{$expectedWebhookUrlKey}'.");
            return $this->webhookHelper->webhookHttpResponse("Error: Webhook URL Key", 404);
        }

        $body = @file_get_contents('php://input');
        $response = json_decode($body);

        $this->webhookLogger->info("webhookUrlKey ve expectedWebhookUrlKey Eşit", [
            'body' => $body,
            'response' => $response
        ]);

        if (isset($response->iyziEventType) && isset($response->token)) {
            $token = $response->token;
            $iyziEventType = $response->iyziEventType;
            $createIyzicoSignature = base64_encode(sha1($this->webhookHelper->getSecretKey() . $iyziEventType . $token, true));
            if ($createIyzicoSignature) {
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
     */
    public function getHttpResponse(stdClass $response)
    {
        $this->webhookLogger->info("Webhook Response: ", (array) $response);
        if (!isset($response->iyziEventType) || !isset($response->token) || !isset($response->paymentConversationId)) {
            $this->logger->error("Error: iyziEventType, token, paymentConversationId not found in the response");
            return $this->webhookHelper->webhookHttpResponse("Error: iyziEventType, token, paymentConversationId not found in the response", 404);
        }
        return $this->iyzipayResponse->webhook($response->token, $response->iyziEventType);
    }
}
