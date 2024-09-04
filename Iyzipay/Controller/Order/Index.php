<?php

namespace Iyzico\Iyzipay\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Index extends Action
{
    protected $resultPageFactory;
    protected $orderRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->orderRepository = $orderRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');

        try {
            $order = $this->orderRepository->get($orderId);
            $resultPage->getLayout()->getBlock('iyzipay_order_result')->setData('order', $order);
        } catch (NoSuchEntityException $e) {
            $resultPage->getLayout()->getBlock('iyzipay_order_result')->setData('error_message', __('Order not found.'));
        } catch (\Exception $e) {
            $resultPage->getLayout()->getBlock('iyzipay_order_result')->setData('error_message', __('An error occurred while processing your request.'));
        }

        return $resultPage;
    }
}
