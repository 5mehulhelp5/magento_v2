<?php

/**
 * iyzico Payment Gateway For Magento 2
 * Copyright (C) 2018 iyzico
 *
 * This file is part of Iyzico/Iyzipay.
 *
 * Iyzico/Iyzipay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Iyzico\Iyzipay\Controller\Response;

use Exception;
use Throwable;
use Iyzico\Iyzipay\Enums\ErrorCode;
use Iyzico\Iyzipay\Helper\PkiStringBuilder;
use Iyzico\Iyzipay\Helper\PkiStringBuilderFactory;
use Iyzico\Iyzipay\Helper\PriceHelper;
use Iyzico\Iyzipay\Helper\RequestHelper;
use Iyzico\Iyzipay\Helper\RequestHelperFactory;
use Iyzico\Iyzipay\Helper\ResponseObjectHelper;
use Iyzico\Iyzipay\Helper\WebhookHelper;
use Iyzico\Iyzipay\Helper\WebhookHelperFactory;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Iyzico\Iyzipay\Model\ResourceModel\IyziOrderJob\Collection as IyziOrderJobCollection;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;

class IyzipayResponse extends Action implements CsrfAwareActionInterface
{
    protected Context $context;
    protected CheckoutSession $checkoutSession;
    protected CustomerSession $customerSession;
    protected TemplateContext $templateContext;
    protected CartManagementInterface $cartManagement;
    protected OrderRepositoryInterface $orderRepository;
    protected $resultFactory;
    protected ScopeConfigInterface $scopeConfig;
    protected IyziCardFactory $iyziCardFactory;
    protected $messageManager;
    protected StoreManagerInterface $storeManager;
    protected PriceHelper $priceHelper;
    protected IyziErrorLogger $errorLogger;
    protected ResponseObjectHelper $responseObjectHelper;
    protected IyziOrderJobCollection $iyziOrderJobCollection;
    protected WebhookHelperFactory $webhookHelperFactory;
    protected PkiStringBuilderFactory $pkiStringBuilderFactory;
    protected RequestHelperFactory $requestHelperFactory;

    public function __construct
    (
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $orderRepository,
        ResultFactory $resultFactory,
        ScopeConfigInterface $scopeConfig,
        IyziCardFactory $iyziCardFactory,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper,
        IyziErrorLogger $errorLogger,
        ResponseObjectHelper $responseObjectHelper,
        IyziOrderJobCollection $iyziOrderJobCollection,
        WebhookHelperFactory $webhookHelperFactory,
        PkiStringBuilderFactory $pkiStringBuilderFactory,
        RequestHelperFactory $requestHelperFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->cartManagement = $cartManagement;
        $this->orderRepository = $orderRepository;
        $this->resultFactory = $resultFactory;
        $this->scopeConfig = $scopeConfig;
        $this->iyziCardFactory = $iyziCardFactory;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->priceHelper = $priceHelper;
        $this->errorLogger = $errorLogger;
        $this->responseObjectHelper = $responseObjectHelper;
        $this->iyziOrderJobCollection = $iyziOrderJobCollection;
        $this->webhookHelperFactory = $webhookHelperFactory;
        $this->pkiStringBuilderFactory = $pkiStringBuilderFactory;
        $this->requestHelperFactory = $requestHelperFactory;
    }


    /**
     * Create Csrf Validation Exception
     *
     * This function is responsible for creating the csrf validation exception.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $params = $request->getParams();
        $this->errorLogger->critical("createCsrfValidationException: " . json_encode($params), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        return null;
    }

    /**
     * Validate For Csrf
     *
     * This function is responsible for validating the csrf.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function execute()
    {
        return $this->response();
    }

    /**
     *
     *
     * @throws Exception
     */
    public function response()
    {
        try {
            $token = $this->getToken();
            $orderId = $this->findParametersByToken($token, 'order_id');
            $order = $this->findOrderById($orderId);
            $response = $this->getPaymentDetail($token);

            $this->updateOrderPaymentStatus($orderId, $response);
            $this->updateOrderJobPaymentId($orderId, $response);

            if ($this->getUserId() != 0) {
                $this->setUserCard($response);
            }

            if (isset($response->errorCode)) {
                return $this->handleError($response, $this->resultRedirectFactory->create());
            }

            $this->checkoutSession->setLastQuoteId($order->getQuoteId())
                ->setLastSuccessQuoteId($order->getQuoteId())
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());

            $this->checkoutSession->getQuote()->setIsActive(false)->save();

            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);

        } catch (Exception $e) {
            $this->errorLogger->critical("execute error: " . $e->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment. Please try again.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        }

    }

    /**
     * Process Webhook Response
     *
     * This function is responsible for processing the webhook response.
     *
     * @throws Exception
     */
    public function webhook(string $token, string $iyziEventType)
    {
        $orderId = $this->findParametersByToken($token, 'order_id');
        $response = $this->getPaymentDetail($token);

        $this->updateOrderFromWebhook($iyziEventType, $orderId, $response);

        if (isset($response->errorCode)) {
            $this->handleWebhookError($response);
        }
    }

    /**
     * Update Order Payment Status
     *
     * This function is responsible for updating the order payment status based on the response.
     *
     * @param string $orderId
     * @param object $response
     *
     * @return void
     * @throws Exception
     */
    private function updateOrderPaymentStatus(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);

        $paymentStatus = $response->paymentStatus;
        $status = $response->status;

        if ($paymentStatus == 'PENDING_CREDIT' && $status == 'success') {
            $order->setState("pending_payment")->setStatus("pending_payment");
            $order->addStatusHistoryComment(__("PENDING_CREDIT"));
            $this->setOrderJobStatus($orderId, "pending_payment");
        }

        if ($paymentStatus == 'INIT_BANK_TRANSFER' && $status == 'success') {
            $order->setState("pending_payment")->setStatus("pending_payment");
            $order->addStatusHistoryComment(__("INIT_BANK_TRANSFER"));
            $this->setOrderJobStatus($orderId, "pending_payment");
        }

        if ($paymentStatus == 'SUCCESS' && $status == 'success') {
            $order->setState("processing")->setStatus("processing");
            $order->addStatusHistoryComment(__("SUCCESS"));
            $this->setOrderJobStatus($orderId, "processing");
        }

        if ($response->installment > 1) {
            $order = $this->setOrderInstallmentFee($order, $response->paidPrice, $response->installment);
        }

        $order->addStatusHistoryComment("Payment ID:" . $response->paymentId);
        $order->addStatusHistoryComment("Conversation ID:" . $response->conversationId);

        $order->save();
    }

    /**
     * Update Order Job Payment Id
     *
     * This function is responsible for updating the order payment status based on the response.
     *
     * @param string $orderId
     * @param object $response
     *
     * @return void
     * @throws Exception
     */
    private function updateOrderJobPaymentId(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);
        $paymentId = $response->paymentId;
        $this->setOrderJobPaymentId($orderId, $paymentId);
        $order->save();
    }

    /**
     * Update Order Payment Status From Webhook
     *
     * This function is responsible for updating the order payment status from the webhook.
     *
     * @param string $iyziEventType
     * @param string $orderId
     * @param object $response
     *
     * @return void
     * @throws Exception
     */
    private function updateOrderFromWebhook(string $iyziEventType, string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);

        $defaultState = $order->getState();
        $defaultStatus = $order->getStatus();

        $orderStatusDetails = $this->findOrderStatusDetails($iyziEventType, $response->status, $response->paymentStatus);

        $order->setState($orderStatusDetails['state'] ?? $defaultState);
        $order->setStatus($orderStatusDetails['status'] ?? $defaultStatus);
        $order->addStatusHistoryComment($orderStatusDetails['comment'] ?? "The order status has been updated by the webhook.");

        if ($response->installment > 1) {
            $order = $this->setOrderInstallmentFee($order, $response->paidPrice, $response->installment);
        }

        $order->save();
        $this->setOrderJobStatus($orderId, $orderStatusDetails['state'] ?? $defaultState);
    }

    /**
     * Find Parameters By Token
     *
     * This function is responsible for finding the parameters by token.
     *
     * @param string $token
     * @return mixed
     */
    private function findParametersByToken(string $token, string $find): mixed
    {
        $iyzicoOrderJob = $this->iyziOrderJobCollection->addFieldToFilter('iyzico_payment_token', $token)->getFirstItem();
        return $iyzicoOrderJob->getData($find);
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
     * Find Order Status Details
     *
     * This function is responsible for finding the order status details.
     *
     * @param string $iyziEventType
     * @param string $status
     * @param string $paymentStatus
     * @return array
     */
    private function findOrderStatusDetails(string $iyziEventType, string $status, string $paymentStatus): array
    {
        if ($iyziEventType === 'BANK_TRANSFER_AUTH' && $status === 'success') {
            return [
                'state' => 'processing',
                'status' => 'processing',
                'comment' => __("BANK_TRANSFER_AUTH_SUCCESS")
            ];
        }

        if ($iyziEventType === 'CREDIT_PAYMENT_INIT' && $status === 'INIT_CREDIT') {
            return [
                'state' => 'pending_payment',
                'status' => 'pending_payment',
                'comment' => __("INIT_CREDIT")
            ];
        }

        if ($iyziEventType === 'CREDIT_PAYMENT_PENDING' && $paymentStatus === 'PENDING_CREDIT') {
            return [
                'state' => 'pending_payment',
                'status' => 'pending_payment',
                'comment' => __("CREDIT_PAYMENT_PENDING")
            ];
        }

        if ($iyziEventType === 'CREDIT_PAYMENT_AUTH' && $status === 'success') {
            return [
                'state' => 'processing',
                'status' => 'processing',
                'comment' => __("CREDIT_PAYMENT_AUTH_SUCCESS")
            ];
        }

        if ($iyziEventType === 'CREDIT_PAYMENT_AUTH' && $status === 'FAILURE') {
            return [
                'state' => 'canceled',
                'status' => 'canceled',
                'comment' => __("CREDIT_PAYMENT_AUTH_FAILURE")
            ];
        }

        return [];
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
    private function setOrderInstallmentFee($order, $paidPrice, $installment)
    {
        $grandTotal = $order->getGrandTotal();

        $installmentPrice = $this->priceHelper->calculateInstallmentPrice($paidPrice, $grandTotal);

        $order->setInstallmentFee($installmentPrice);
        $order->setInstallmentCount($installment);

        return $order;
    }

    /**
     * Set Iyzipay Order Job
     *
     * This function is responsible for saving the iyzi order.
     *
     * @param object $response
     * @param string $orderId
     * @return void
     */
    private function setIyziOrder(object $response, string $orderId)
    {
        try {
            // Load the order by order ID
            $order = $this->orderRepository->get($orderId);

            // Get the payment object from the order
            $payment = $order->getPayment();

            // Set the iyzico_payment_id and iyzico_conversation_id fields
            $payment->setData('iyzico_payment_id', $response->paymentId);
            $payment->setData('iyzico_conversation_id', $response->conversationId);

            // Save the updated order payment data
            $this->orderRepository->save($order);

        } catch (Throwable $th) {
            $this->errorLogger->critical("setIyziOrder: " . $th->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        }
    }

    /**
     * Set Iyzipay Order Job
     *
     * This function is responsible for saving the iyzi order job.
     *
     * @param string $orderId
     * @param string $status
     * @return void
     */
    private function setOrderJobStatus(string $orderId, string $status)
    {
        $iyziOrderJob = $this->iyziOrderJobCollection->addFieldToFilter('order_id', $orderId)->getFirstItem();
        $iyziOrderJob->setStatus($status);
        try {
            if ($status == 'processing' || $status == 'canceled') {
                $iyziOrderJob->delete();
            } else {
                $iyziOrderJob->save();
            }
        } catch (Throwable $th) {
            $this->errorLogger->critical("setIyziOrderJob: " . $th->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        }
    }

    /**
     * Set Iyzipay Order Job
     *
     * This function is responsible for saving the iyzi order job.
     *
     * @param string $orderId
     * @param string $status
     * @return void
     */
    private function setOrderJobPaymentId(string $orderId, string $paymentId)
    {
        $iyziOrderJob = $this->iyziOrderJobCollection->addFieldToFilter('order_id', $orderId)->getFirstItem();
        $iyziOrderJob->setIyzicoPaymentId($paymentId);
        try {
            $iyziOrderJob->save();
        } catch (Throwable $th) {
            $this->errorLogger->critical("setIyziOrderJob: " . $th->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        }
    }

    /**
     * Retrive Payment Detail
     *
     * This function is responsible for retriving the payment detail.
     *
     * @param string $token
     * @return object
     */
    public function getPaymentDetail(string $token)
    {
        $defination = $this->getPaymentDefinition();
        $pkiStringBuilder = $this->getPkiStringBuilder();
        $requestHelper = $this->getRequestHelper();
        $conversationId = $this->findParametersByToken($token, 'iyzico_conversationid');

        $tokenDetailObject = $this->responseObjectHelper->createTokenDetailObject($conversationId, $token);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($tokenDetailObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);
        $iyzicoJson = json_encode($tokenDetailObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $requestHelper->sendCheckoutFormDetailRequest($defination['baseUrl'], $iyzicoJson, $authorization);
    }

    /**
     * Get Payment Definition
     *
     * This function is responsible for getting the payment definition.
     *
     * @return array
     */
    private function getPaymentDefinition()
    {
        $storeId = $this->storeManager->getStore()->getId();

        return [
            'rand' => uniqid(),
            'baseUrl' => $this->scopeConfig->getValue(
                'payment/iyzipay/sandbox',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->scopeConfig->getValue(
                'payment/iyzipay/api_key',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'secretKey' => $this->scopeConfig->getValue(
                'payment/iyzipay/secret_key',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        ];
    }

    /**
     * Get User Id
     *
     * This function is responsible for checking if the user is logged in.
     *
     * @return int
     */
    private function getUserId(): int
    {
        if (!$this->customerSession->isLoggedIn()) {
            return 0;
        } else {
            return $this->customerSession->getCustomerId();
        }
    }

    /**
     * Get Token
     *
     * This function is responsible for validating the token and returning the result.
     *
     * @return string
     */
    private function getToken()
    {
        return $this->getRequest()->getPostValue()['token'];
    }

    /**
     * Get Webhook Helper
     *
     * This function is responsible for getting the webhook helper.
     *
     * @return WebhookHelper
     */
    private function getWebhookHelper(): WebhookHelper
    {
        return $this->webhookHelperFactory->create();
    }

    /**
     * Get Pki String Builder
     *
     * This function is responsible for getting the pki string builder.
     *
     * @return PkiStringBuilder
     */
    private function getPkiStringBuilder(): PkiStringBuilder
    {
        return $this->pkiStringBuilderFactory->create();
    }

    /**
     * Get Request Helper
     *
     * This function is responsible for getting the request helper.
     *
     * @return RequestHelper
     */
    private function getRequestHelper(): RequestHelper
    {
        return $this->requestHelperFactory->create();
    }

    /**
     * Get Iyzipay Module order_status from configuration : TODO
     *
     * This function is responsible for getting the order status from the configuration.
     *
     * @return string
     */
    private function getIyzipayOrderStatus(): string
    {
        return $this->scopeConfig->getValue('payment/iyzipay/order_status');
    }

    /**
     * Handle Webhook Response
     *
     * This function is responsible for handling the webhook response.
     *
     * @param object $response
     * @return void
     */
    private function handleWebhookError(object $response): void
    {
        $webhookHelper = $this->getWebhookHelper();
        $status = $response->status;
        $paymentStatus = $response->paymentStatus;

        if ($status == 'failure' && $paymentStatus != 'SUCCESS') {
            $errorCode = ErrorCode::from($response->errorCode);
            $errorMessage = $errorCode->getErrorMessage();
            $webhookHelper->webhookHttpResponse($response->errorCode . '-' . $errorMessage, 404);
            $this->errorLogger->critical("handleWebhookError: " . $response->errorCode . '-' . $errorMessage, ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
        }
    }

    /**
     * Handle Error Response
     *
     * This function is responsible for handling the error response.
     *
     * @param object $response
     * @param $resultRedirect
     * @return mixed
     */
    private function handleError(object $response, $resultRedirect): mixed
    {
        $errorCode = ErrorCode::from($response->errorCode);
        $errorMessage = $errorCode->getErrorMessage();

        $this->errorLogger->critical("handleError: " . $response->errorCode . '-' . $errorMessage, ['fileName' => __FILE__, 'lineNumber' => __LINE__]);

        $this->messageManager->addError($errorMessage);
        return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
    }

    /**
     * Save User Card
     *
     * This function is responsible for saving the user card.
     *
     * @param object $response
     * @return bool
     * @throws Exception
     */
    private function setUserCard(object $response): bool
    {
        $defination = $this->getPaymentDefinition();
        $customerId = $this->getUserId();

        if (isset($response->cardUserKey) && $customerId != 0) {
            $iyziCardFind = $this->iyziCardFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('api_key', $defination['apiKey'])
                ->addFieldToSelect('card_user_key');

            $iyziCardFind = $iyziCardFind->getData();

            $customerCardUserKey = !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : null;

            if ($response->cardUserKey != $customerCardUserKey) {
                $iyziCardModel = $this->iyziCardFactory->create([
                    'customer_id' => $customerId,
                    'card_user_key' => $response->cardUserKey,
                    'api_key' => $defination['apiKey'],
                ]);
                $iyziCardModel->save();
            }
            return true;
        }

        return false;
    }
}
