<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\ObjectManagerInterface;

class RequestHelperFactory
{
    private $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function create(): RequestHelper
    {
        return $this->objectManager->create(RequestHelper::class);
    }
}
