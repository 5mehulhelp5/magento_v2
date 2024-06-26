<?php
namespace Iyzico\Iyzipay\Controller\Test;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Iyzico\Iyzipay\Cron\ProcessPendingOrders;

class Cron extends Action
{
    protected $processPendingOrders;
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        ProcessPendingOrders $processPendingOrders,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->processPendingOrders = $processPendingOrders;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $cronResult = $this->processPendingOrders->execute();
            return $result->setData(['success' => true, 'data' => $cronResult]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
