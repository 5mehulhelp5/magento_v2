<?php

namespace Iyzico\Iyzipay\Infrastructure\Contracts;

use stdClass;

interface WebhookInterface
{
    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     * @return void
     */
    public function getResponse(string $webhookUrlKey): void;

    /**
     * Get the HTTP response based on the webhook response.
     *
     * @param stdClass $response The webhook response object.
     * @return void The HTTP response.
     */
    public function getHttpResponse(stdClass $response): void;
}
