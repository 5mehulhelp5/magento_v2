<?php

namespace Iyzico\Iyzipay\Infrastructure\Contracts;

use stdClass;

interface WebhookInterface
{
    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     */
    public function getResponse(string $webhookUrlKey);
}
