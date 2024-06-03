<?php

namespace Iyzico\Iyzipay\Logger\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class IyziHandler extends StreamHandler
{
    public function __construct()
    {
        $filePath = BP . '/var/log/iyzipay_error.log';
        parent::__construct($filePath, Logger::DEBUG);
    }
}
