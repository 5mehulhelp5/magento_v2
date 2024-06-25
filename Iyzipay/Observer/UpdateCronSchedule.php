<?php

namespace Iyzico\Iyzipay\Observer;

use Iyzico\Iyzipay\Logger\IyziLogger;
use Magento\Framework\Event\ObserverInterface;
use Iyzico\Iyzipay\Helper\CronHelper;

class UpdateCronSchedule implements ObserverInterface
{
    private $cronHelper;
    private $logger;

    public function __construct(CronHelper $cronHelper, IyziLogger $logger)
    {
        $this->cronHelper = $cronHelper;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $newSchedule = $this->cronHelper->getCronSchedule();
        $this->logger->info('Cron schedule updated: ' . $newSchedule);
    }
}
