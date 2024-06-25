<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\ObjectManagerInterface;

class WebhookHelperFactory
{
    private $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function create(): WebhookHelper
    {
        return $this->objectManager->create(WebhookHelper::class);
    }
}
