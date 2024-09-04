<?php

namespace Iyzico\Iyzipay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Iyzico\Iyzipay\Controller\Request\IyzipayRequest;
use Iyzico\Iyzipay\Model\ResourceModel\IyziOrderJob\Collection as IyziOrderJobCollection;

class Retry extends Action
{
    protected $orderRepository;
    protected $resultJsonFactory;
    protected $messageManager;
    protected $iyzipayRequest;
    protected $iyziOrderJobCollection;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        JsonFactory $resultJsonFactory,
        ManagerInterface $messageManager,
        IyzipayRequest $iyzipayRequest,
        IyziOrderJobCollection $iyziOrderJobCollection
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->messageManager = $messageManager;
        $this->iyzipayRequest = $iyzipayRequest;
        $this->iyziOrderJobCollection = $iyziOrderJobCollection;
    }

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order');

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('No order ID provided.'));
            return $this->_redirect('*/*/');
        }

        $iyziOrder = $this->findByOrderId($orderId);

        try {
            $order = $this->orderRepository->get($orderId);

            if ($order->getState() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
                // IyzipayRequest'i kullanarak ödeme isteği oluştur
                $result = $this->iyzipayRequest->execute($order);

                if(isset($result->token))
                {
                    $iyziOrder->setData('iyzico_payment_token', $result->token);
                    $iyziOrder->save();
                }

                if (isset($result->paymentPageUrl)) {
                    // Doğrudan yönlendirme yap
                    return $this->_redirect($result->paymentPageUrl);
                } else {
                    $this->messageManager->addErrorMessage(__('Payment request failed. Please try again.'));
                    return $this->_redirect('*/*/');
                }
            } else {
                $this->messageManager->addErrorMessage(__('Order #%1 is not eligible for payment retry.', $order->getIncrementId()));
                return $this->_redirect('*/*/');
            }

        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->_redirect('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your request: ') . $e->getMessage());
            return $this->_redirect('*/*/');
        }
    }

    /**
     * Find Parameters By OrderId
     *
     * This function is responsible for finding the parameters by orderId.
     *
     * @param string $token
     * @return mixed
     */
    private function findByOrderId(string $orderId): mixed
    {
        return $this->iyziOrderJobCollection->addFieldToFilter('order_id', $orderId)->getFirstItem();
    }
}
