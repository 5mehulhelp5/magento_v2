<?php

namespace Iyzico\Iyzipay\Api;

use Iyzico\Iyzipay\Model\Data\WebhookData;

interface WebhookInterface
{
    /**
     * Check Webhook URL Key
     *
     * @param  string  $webhookUrlKey
     * @return void
     */
    public function check(string $webhookUrlKey): void;

    /**
     * Get Webhook Headers
     *
     * @return string
     */
    public function getWebhookHeader(): string;

    /**
     * Get Webhook Body
     *
     * @return WebhookData
     */
    public function getWebhookBody(): WebhookData;

    /**
     * Validate Webhook Signature
     *
     * @param  string  $signature
     * @param  string  $payload
     * @return bool
     */
    public function validateSignature(string $signature, string $payload): bool;

    /**
     * Process Webhook
     *
     * @param  array  $data
     * @return mixed
     */
    public function processWebhook(array $data): mixed;

    /**
     * Process Webhook V3
     *
     * @param  array  $data
     * @return mixed
     */
    public function processWebhookV3(array $data): mixed;

    /**
     * Log Webhook Event
     *
     * @param  string  $eventType
     * @param  array  $data
     * @param  string  $status
     * @return void
     */
    public function logWebhookEvent(string $eventType, array $data, string $status): void;

    /**
     * Generate Key
     *
     * @param  string  $secretKey
     * @param  WebhookData  $webhookData
     * @return string
     */
    public function generateKey(string $secretKey, WebhookData $webhookData): string;
}
