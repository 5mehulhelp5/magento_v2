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
use Iyzico\Iyzipay\Helper\PkiStringBuilder;
use Iyzico\Iyzipay\Helper\PriceHelper;
use Iyzico\Iyzipay\Helper\RequestHelper;
use Iyzico\Iyzipay\Helper\ResponseObjectHelper;
use Iyzico\Iyzipay\Helper\WebhookHelper;
use Iyzico\Iyzipay\Logger\IyziLogger;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Iyzico\Iyzipay\Model\IyziOrderFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Iyzico\Iyzipay\Model\ResourceModel\IyziOrderJob\Collection as IyziOrderJobCollection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Iyzico\Iyzipay\Enums\ErrorCode;

class IyzicoCheckoutForm extends Action implements CsrfAwareActionInterface
{
    protected Context $_context;
    protected CheckoutSession $_checkoutSession;
    protected CustomerSession $_customerSession;
    protected CartManagementInterface $_cartManagement;
    protected ResultFactory $_resultRedirect;
    protected ScopeConfigInterface $_scopeConfig;
    protected IyziOrderFactory $_iyziOrderFactory;
    protected IyziCardFactory $_iyziCardFactory;
    protected ManagerInterface $_messageManager;
    protected StoreManagerInterface $_storeManager;
    protected PriceHelper $_priceHelper;
    protected IyziLogger $_iyziLogger;
    protected ResponseObjectHelper $_responseObjectHelper;
    protected TemplateContext $_templateContext;
    protected OrderRepositoryInterface $_orderRepository;

    public function __construct
    (
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory,
        IyziCardFactory $iyziCardFactory,
        IyziOrderFactory $iyziOrderFactory,
        PriceHelper $priceHelper,
        IyziLogger $iyziLogger,
        ResponseObjectHelper $responseObjectHelper,
        TemplateContext $templateContext
    ) {
        parent::__construct($context);
        $this->_templateContext = $templateContext;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_cartManagement = $cartManagement;
        $this->_orderRepository = $orderRepository;
        $this->_resultRedirect = $resultFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_iyziOrderFactory = $iyziOrderFactory;
        $this->_iyziCardFactory = $iyziCardFactory;
        $this->_messageManager = $messageManager;
        $this->_storeManager = $storeManager;
        $this->_priceHelper = $priceHelper;
        $this->_responseObjectHelper = $responseObjectHelper;
        $this->_iyziLogger = $iyziLogger;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        return $this->processPaymentResponse();
    }


    /**
     * @param  $webhook
     * @param  $webhookPaymentConversationId
     * @param  $webhookToken
     * @param  $webhookIyziEventType
     */
    public function processPaymentResponse($webhook = null, $webhookPaymentConversationId = null, $webhookToken = null, $webhookIyziEventType = null)
    {
        // iyzico_order_job kayıtlar silinmeli.
        // iyzico_order_job cron job ile retrive isteği atılacak. Eğer başarılı olursa kayıt silinecek. Başarısız olursa 4 saat sonra tekrar denenecek. 3 defa denedikten sonra kayıt silinecek.
        // Taksit kodlarını incele.
        // success page gitmiyor ona bak.

        $defination = $this->getPaymentDefinition();

        $pkiStringBuilder = $this->getPkiStringBuilder();
        $requestHelper = $this->getRequestHelper();

        $resultRedirect = $this->_resultRedirect->create(ResultFactory::TYPE_REDIRECT);

        $token = $this->getToken($webhook, $webhookToken, $resultRedirect);

        $iyzicoOrderJobCollection = $this->_objectManager->create(IyziOrderJobCollection::class);
        $iyzicoOrderJob = $iyzicoOrderJobCollection->addFieldToFilter('iyzico_payment_token', $token)->getFirstItem();

        $conversationId = $iyzicoOrderJob->getData('iyzico_conversationId');
        $orderId = $iyzicoOrderJob->getData('magento_order_id');
        $defination['customerId'] = $this->_customerSession->isLoggedIn() ? $this->_customerSession->getCustomerId() : 0;

        $tokenDetailObject = $this->_responseObjectHelper->createTokenDetailObject($conversationId, $token);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($tokenDetailObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);
        $iyzicoJson = json_encode($tokenDetailObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestResponse = $requestHelper->sendCheckoutFormDetailRequest($defination['baseUrl'], $iyzicoJson, $authorization);


        if ($webhook !== null) {
            $this->handleWebhookResponse($requestResponse);
        }

        if (isset($requestResponse->errorCode)) {
            return $this->handleErrorResponse($requestResponse, $resultRedirect);
        }

        $processOrder = match ($webhookIyziEventType) {
            'CREDIT_PAYMENT_INIT' => $this->processCreditPaymentInit($orderId, $requestResponse),
            'CREDIT_PAYMENT_PENDING' => $this->processCreditPaymentPending($orderId, $requestResponse),
            'CREDIT_PAYMENT_AUTH' => $this->processCreditPaymentAuth($orderId, $requestResponse),
            'BANK_TRANSFER_AUTH' => $this->processBankTransferAuth($orderId, $requestResponse),
            default => $this->processDefault($orderId, $requestResponse, $defination),
        };

        if ($processOrder) {
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
            return $resultRedirect;
        }


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
        return [
            'rand' => uniqid(),
            'customerId' => 0,
            'customerCardUserKey' => '',
            'baseUrl' => $this->_scopeConfig->getValue('payment/iyzipay/sandbox') ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->_scopeConfig->getValue('payment/iyzipay/api_key'),
            'secretKey' => $this->_scopeConfig->getValue('payment/iyzipay/secret_key'),
        ];
    }

    /**
     * Get Pki String Builder
     *
     * This function is responsible for getting the pki string builder.
     *
     * @return PkiStringBuilder
     */
    private function getPkiStringBuilder()
    {
        return new PkiStringBuilder();
    }

    /**
     * Get Request Helper
     *
     * This function is responsible for getting the request helper.
     *
     * @return RequestHelper
     */
    private function getRequestHelper()
    {
        return new RequestHelper();
    }

    /**
     * Get Webhook Helper
     *
     * This function is responsible for getting the webhook helper.
     *
     * @return WebhookHelper
     */
    private function getWebhookHelper()
    {
        return new WebhookHelper($this->_templateContext);
    }

    /**
     * Get Object Manager
     *
     * This function is responsible for getting the object manager.
     *
     * @return ObjectManager
     */
    private function instanceObjectManager()
    {
        return ObjectManager::getInstance();
    }

    /**
     * Get Token
     *
     * This function is responsible for validating the token and returning the result.
     *
     * @param string $webhook
     * @param string $webhookToken
     * @param $resultRedirect
     * @return string
     */
    private function getToken($webhook, $webhookToken, $resultRedirect)
    {
        $postData = $this->getRequest()->getPostValue();

        if (!isset($postData['token']) && $webhook != 'webhook') {
            $errorMessage = __('Token not found');

            $this->_messageManager->addError($errorMessage);
            return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        }

        return ($webhook == 'webhook') ? $webhookToken : $postData['token'];
    }

    /**
     * Process Credit Payment Init
     *
     * This function is responsible for processing the credit payment init.
     *
     * @param string $orderId
     * @param object $response
     */
    private function processCreditPaymentInit(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);
        $status = $response->status;
        $comment = __("INIT_CREDIT");


        if ($status == 'INIT_CREDIT') {
            $order->setState('pending');
            $order->setStatus('pending');
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    /**
     * Process Credit Payment Pending
     *
     * This function is responsible for processing the credit payment pending.
     *
     * @param string $orderId
     * @param object $response
     * @return void
     */
    private function processCreditPaymentPending(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);
        $status = $response->paymentStatus;
        $comment = __("CREDIT_PAYMENT_PENDING");

        if ($status == 'PENDING_CREDIT') {
            $order->setState('pending');
            $order->setStatus('pending');
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    /**
     * Process Credit Payment Auth
     *
     * This function is responsible for processing the credit payment auth.
     *
     * @param string $orderId
     * @param object $response
     * @return void
     */
    private function processCreditPaymentAuth(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);
        $status = $response->status;

        if ($status == 'success') {
            $order->setState('processing');
            $order->setStatus('processing');
            $comment = __("CREDIT_PAYMENT_AUTH_SUCCESS");
        }

        if ($status == 'FAILURE') {
            $order->setState('canceled');
            $order->setStatus('canceled');
            $comment = __("CREDIT_PAYMENT_AUTH_FAILURE");
        }

        $order->addStatusHistoryComment($comment);
        $order->save();
    }

    /**
     * Process Bank Transfer Auth
     *
     * This function is responsible for processing the bank transfer auth.
     *
     * @param string $orderId
     * @param object $response
     * @return void
     */
    private function processBankTransferAuth(string $orderId, object $response): void
    {
        $order = $this->findOrderById($orderId);
        $status = $response->status;
        $paymentStatus = $response->paymentStatus;
        $comment = __("BANK_TRANSFER_AUTH_SUCCESS");

        if ($status == 'success' && $paymentStatus == 'SUCCESS') {
            $order->setState('processing');
            $order->setStatus('processing');
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    /**
     * Process Default
     *
     * This function is responsible for processing the default.
     *
     * @param string $orderId
     * @param object $response
     * @param array $defination
     *
     * @return bool
     */
    private function processDefault(string $orderId, object $response, array $defination): bool
    {
        $order = $this->findOrderById($orderId);
        $paymentStatus = $response->paymentStatus;
        $status = $response->status;

        if ($paymentStatus == 'PENDING_CREDIT' && $status == 'success') {
            $order->addStatusHistoryComment(__("PENDING_CREDIT"));
        }

        if ($paymentStatus == 'INIT_BANK_TRANSFER' && $status == 'success') {
            $order->addStatusHistoryComment(__("INIT_BANK_TRANSFER"));
        }

        if ($paymentStatus == 'SUCCESS' && $status == 'success') {
            $order->addStatusHistoryComment(__("SUCCESS"));
            $order->setState('processing');
            $order->setStatus('processing');
        }

        $order->save();

        if (isset($response->cardUserKey) && $defination['customerId'] != 0) {
            $this->saveUserCard($defination, $response);
        }

        return true;
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
            return $this->_orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            $this->_iyziLogger->critical("findOrderById: $orderId - Message: " . $e->getMessage(), ['fileName' => __FILE__, 'lineNumber' => __LINE__]);
            return null;
        }
    }

    /**
     * Handle Webhook Response
     *
     * This function is responsible for handling the webhook response.
     *
     * @param object $response
     * @return void
     */
    private function handleWebhookResponse(object $response): void
    {
        $webhookHelper = $this->getWebhookHelper();
        $status = $response->status;
        $paymentStatus = $response->paymentStatus;

        if ($status == 'failure' && $paymentStatus != 'SUCCESS') {
            $errorCode = ErrorCode::from($response->errorCode);
            $errorMessage = $errorCode->getErrorMessage();
            $webhookHelper->webhookHttpResponse($response->errorCode . '-' . $errorMessage, 404);
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
    private function handleErrorResponse(object $response, $resultRedirect): mixed
    {
        $errorCode = ErrorCode::from($response->errorCode);
        $errorMessage = $errorCode->getErrorMessage();

        $this->_messageManager->addError($errorMessage);
        return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
    }

    /**
     * Save User Card
     *
     * This function is responsible for saving the user card.
     *
     * @param array $defination
     * @param object $response
     * @return void
     */
    private function saveUserCard(array $defination, object $response): void
    {
        $iyziCardFind = $this->_iyziCardFactory->create()->getCollection()
            ->addFieldToFilter('customer_id', $defination['$customerId'])
            ->addFieldToFilter('api_key', $defination['apiKey'])
            ->addFieldToSelect('card_user_key');

        $iyziCardFind = $iyziCardFind->getData();

        $defination['customerCardUserKey'] = !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : null;

        if ($response->cardUserKey != $defination['customerCardUserKey']) {

            /* Customer Card Save */
            $iyziCardModel = $this->_iyziCardFactory->create([
                'customer_id' => $defination['$customerId'],
                'card_user_key' => $response->cardUserKey,
                'api_key' => $defination['apiKey'],
            ]);

            $iyziCardModel->save();
        }
    }
}
