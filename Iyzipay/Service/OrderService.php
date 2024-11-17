<?php

namespace Iyzico\Iyzipay\Service;

use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote;
use Iyzico\Iyzipay\Helper\UtilityHelper;
use Iyzico\Iyzipay\Library\Model\CheckoutForm;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Iyzico\Iyzipay\Service\OrderJobService;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;

class OrderService
{

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuoteManagement $quoteManagement,
        private readonly QuoteRepository $quoteRepository,
        private readonly Quote $quoteResource,
        private readonly UtilityHelper $utilityHelper,
        private readonly IyziErrorLogger $errorLogger,
        private readonly OrderJobService $orderJobService
    ) {
    }

    /**
     * Place Order
     *
     * This function is responsible for placing the order and setting the status to pending_payment.
     *
     * @throws CouldNotSaveException
     */
    public function placeOrder(int $quoteId, CustomerSession $customerSession, CartManagementInterface $cartManagement)
    {
        $quote = $this->quoteRepository->get($quoteId);
        if ($customerSession->isLoggedIn()) {
            $orderId = $cartManagement->placeOrder($quoteId);
        } else {
            $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
            $quote->setCustomerEmail($customerSession->getEmail());
            $orderId = $cartManagement->placeOrder($quoteId);
        }

        $order = $this->orderRepository->get($orderId);
        $comment = __("START_ORDER");

        $order->setState('pending_payment')->setStatus('pending_payment');
        $order->addCommentToStatusHistory($comment);
        $order->getPayment()->setMethod('iyzipay');

        $this->orderRepository->save($order);

        return $order;
    }

    /**
     * Update Order Payment Status
     *
     * This function is responsible for updating the order payment status based on the response.
     *
     * @param  string  $orderId
     * @param  CheckoutForm  $response
     *
     * @return void
     * @throws Exception
     */
    public function updateOrderPaymentStatus(string $orderId, CheckoutForm $response): void
    {
        $order = $this->findOrderById($orderId);
        $payment = $order->getPayment();

        $paymentStatus = $response->getPaymentStatus();
        $status = $response->getStatus();

        $payment->setLastTransId($response->getPaymentId());
        $paymentAdditionalInformation = [
            'method_title' => 'Kredi/Banka Kartı ile Ödeme',
            'iyzico_payment_id' => $response->getPaymentId(),
            'iyzico_conversation_id' => $response->getConversationId(),
        ];

        $payment->setAdditionalInformation($paymentAdditionalInformation);

        $payment->setTransactionId($response->getPaymentId())
            ->setIsTransactionClosed(0)
            ->setTransactionAdditionalInfo(
                Transaction::RAW_DETAILS,
                json_encode($paymentAdditionalInformation)
            );

        if ($paymentStatus == 'PENDING_CREDIT' && $status == 'success') {
            $order->setState("pending_payment")->setStatus("pending_payment");
            $order->addCommentToStatusHistory(__("PENDING_CREDIT"));
            $this->orderJobService->setOrderJobStatus($orderId, "pending_payment");
        }

        if ($paymentStatus == 'INIT_BANK_TRANSFER' && $status == 'success') {
            $order->setState("pending_payment")->setStatus("pending_payment");
            $order->addCommentToStatusHistory(__("INIT_BANK_TRANSFER"));
            $this->orderJobService->setOrderJobStatus($orderId, "pending_payment");
        }

        if ($paymentStatus == 'SUCCESS' && $status == 'success') {
            $order->setState("processing")->setStatus("processing");
            $order->addCommentToStatusHistory(__("SUCCESS"));
            $this->orderJobService->setOrderJobStatus($orderId, "processing");
        }

        if ($response->getInstallment() > 1) {
            $order = $this->setOrderInstallmentFee($order, $response->getPaidPrice(), $response->getInstallment());
        }

        $order->addCommentToStatusHistory("Payment ID: " . $response->getPaymentId() . " - Conversation ID:" . $response->getConversationId());
        $order->save();
    }

    /**
     * Find Order By Id
     *
     * This function is responsible for finding the order by id.
     *
     * @param string $orderId
     * @return OrderInterface|null
     */
    private function findOrderById(string $orderId): OrderInterface|null
    {
        try {
            return $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            $this->errorLogger->critical("findOrderById: $orderId - Message: " . $e->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
            return null;
        }
    }

    /**
     * Handle Installment Fee
     *
     * This function is responsible for handling the installment fee.
     *
     * @param $order
     * @param $paidPrice
     * @param $installment
     * @return mixed
     */
    public function setOrderInstallmentFee($order, $paidPrice, $installment)
    {
        $grandTotal = $order->getGrandTotal();

        $installmentPrice = $this->utilityHelper->calculateInstallmentPrice($paidPrice, $grandTotal);

        $order->setInstallmentFee($installmentPrice);
        $order->setInstallmentCount($installment);

        return $order;
    }
}
