<?php

namespace Iyzico\Iyzipay\Api;

interface WebhookInterface
{
    /**
     * Get the response from the webhook URL key.
     *
     * @param string $webhookUrlKey The webhook URL key to get the response for.
     * @return mixed
     */
    public function getResponse(string $webhookUrlKey);
}
