<?php
namespace Iyzico\Iyzipay\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Iyzico\Iyzipay\Cron\ProcessPendingOrders;

class ProcessIyzicoOrders implements HttpGetActionInterface
{
    public function __construct(
        protected JsonFactory $jsonFactory,
        protected ProcessPendingOrders $processPendingOrders
    ) {
    }

    public function execute()
    {
        try {
            $result = $this->processPendingOrders->execute();

            $resultJson = $this->jsonFactory->create();
            return $resultJson->setData($result);
        } catch (\Exception $e) {
            $resultJson = $this->jsonFactory->create();
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
