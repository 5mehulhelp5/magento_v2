<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\ObjectManagerInterface;

class PkiStringBuilderFactory
{
    private $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function create(): PkiStringBuilder
    {
        return $this->objectManager->create(PkiStringBuilder::class);
    }
}
