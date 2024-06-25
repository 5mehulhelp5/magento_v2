<?php
namespace Iyzico\Iyzipay\Cron;

use Iyzico\Iyzipay\Logger\IyziLogger;
use Psr\Log\LoggerInterface;

class ProcessPendingOrders
{
    protected $iyziLogger;
    protected $logger;

    public function __construct(IyziLogger $iyziLogger, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->iyziLogger = $iyziLogger;
    }

    public function execute()
    {
        $time = date('Y-m-d H:i:s');

        $this->logger->info('Cron Works');
        $this->iyziLogger->info('Cron Works Time: ' . $time);
    }
}

// Sipariş durumu pending_payment veya received olan siparişler kontrol edilmeli.
// is_controlled olarak işaretlenmeli.
// table name: iyzi_order_job
