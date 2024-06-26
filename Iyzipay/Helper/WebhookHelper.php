<?php

/**
 * iyzico Payment Gateway For Magento 2
 * Copyright (C) 2018 iyzico
 *
 * This file is part of Iyzico/Iyzipay.
 *
 * Iyzico/Iyzipay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class WebhookHelper
{
    private ScopeConfigInterface $config;

    public function __construct(Context $context)
    {
        $this->config = $context->getScopeConfig();
    }

    /**
     * Get Webhook Url
     *
     * @return mixed
     */
    public function getWebhookUrl(): mixed
    {
        return $this->config->getValue('payment/iyzipay/webhook_url_key', $this->getScopeInterface());
    }

    /**
     * Get Scope Interface
     *
     * @return string
     */
    public function getScopeInterface(): string
    {
        return ScopeInterface::SCOPE_STORE;
    }

    /**
     * Get Secret Key
     *
     * @return mixed
     */
    public function getSecretKey(): mixed
    {
        return $this->config->getValue('payment/iyzipay/secret_key', $this->getScopeInterface());
    }

    /**
     * Webhook Http Response
     *
     * @param  $message
     * @param  $status
     * @return mixed
     */
    public function webhookHttpResponse($message, $status)
    {
        $httpMessage = array('message' => $message, 'status' => $status);
        header('Content-Type: application/json, Status: ' . $status, true, $status);
        echo json_encode($httpMessage);
        exit();
    }


}
