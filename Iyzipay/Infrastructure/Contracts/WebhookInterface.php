<?php

namespace Iyzico\Iyzipay\Infrastructure\Contracts;

use stdClass;

interface WebhookInterface
{
    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     * @return string The response from the webhook.
     */
    public function getResponse(string $webhookUrlKey): string;

    /**
     * Get the HTTP response based on the webhook response.
     *
     * @param stdClass $response The webhook response object.
     * @return string The HTTP response.
     */
    public function getHttpResponse(stdClass $response): string;
}
