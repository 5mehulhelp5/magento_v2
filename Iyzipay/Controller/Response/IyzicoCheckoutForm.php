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

    public function __construct
    (
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CartManagementInterface $cartManagement,
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
        // iyzico_order_job eğer başarılıysa kaydı sil.
        // iyzico_order_job için cron job oluşturulacak.
        // eğer token ve conversationId ile ödeme yoksa kayıt silinecek. Örneğin 1 saat sonra silinecek. Saatlik cron job oluşturulacak.

        try {

            $defination = $this->getPaymentDefinition();
            $pkiStringBuilder = $this->getPkiStringBuilder();
            $requestHelper = $this->getRequestHelper();
            $webhookHelper = $this->getWebhookHelper();

            $postData = $this->getRequest()->getPostValue();
            $resultRedirect = $this->_resultRedirect->create(ResultFactory::TYPE_REDIRECT);
            

            if (!isset($postData['token']) && $webhook != 'webhook') {

                $errorMessage = __('Token not found');

                /* Redirect Error */
                $this->_messageManager->addError($errorMessage);
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                return $resultRedirect;

            }

            if ($webhook == 'webhook') {
                $token = $webhookToken;
                $conversationId = $webhookPaymentConversationId;
            } else {
                $token = $postData['token'];
                $conversationId = "";
            }


            if ($this->_customerSession->isLoggedIn()) {
                $defination['$customerId'] = $this->_customerSession->getCustomerId();
            }

            $tokenDetailObject = $this->_responseObjectHelper->createTokenDetailObject($conversationId, $token);
            $iyzicoPkiString = $pkiStringBuilder->generatePkiString($tokenDetailObject);
            $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);
            $iyzicoJson = json_encode($tokenDetailObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $requestResponse = $requestHelper->sendCheckoutFormDetailRequest($defination['baseUrl'], $iyzicoJson, $authorization);


            if ($webhook == 'webhook' && $requestResponse->status == 'failure' && $requestResponse->paymentStatus != 'SUCCESS') {
                return $webhookHelper->webhookHttpResponse($requestResponse->errorCode . '-' . $requestResponse->errorMessage, 404);
            }


            $objectManager = ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            if ($webhook == 'webhook' && $requestResponse->status == 'success' && $requestResponse->paymentStatus == 'SUCCESS') {
                $tableName = $resource->getTableName('sales_order'); //gives table name with prefix
                $sql = "Select * FROM " . $tableName . " Where quote_id = " . $requestResponse->basketId;
                $result = $connection->fetchAll($sql);

                if ($webhookIyziEventType == 'BANK_TRANSFER_AUTH' && $requestResponse->status == 'success') {
                    $entity_id = $result[0]['entity_id'];
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->load($entity_id);
                    $order->setState('processing');
                    $order->setStatus('processing');
                    $historyComment = 'Bank Transfer success.';
                    $order->addStatusHistoryComment($historyComment);
                    $order->save();
                    return 'ok';
                }
                if (!empty($result)) {
                    return $webhookHelper->webhookHttpResponse("Order Exist - Sipariş zaten var.", 200);
                }
            }


            $requestResponse->paymentId = isset($requestResponse->paymentId) ? (int) $requestResponse->paymentId : '';
            $requestResponse->paidPrice = isset($requestResponse->paidPrice) ? (float) $requestResponse->paidPrice : '';
            $requestResponse->basketId = isset($requestResponse->basketId) ? (int) $requestResponse->basketId : '';

            /* webhook order update credit */
            if ($webhook == 'webhook') {

                $tableName = $resource->getTableName('sales_order');
                $sql = "Select * FROM " . $tableName . " Where quote_id = " . $requestResponse->basketId;
                $result = $connection->fetchAll($sql);
                $entity_id = $result[0]['entity_id'];
                $order = $objectManager->create('\Magento\Sales\Model\Order')->load($entity_id);


                if ($webhookIyziEventType == 'CREDIT_PAYMENT_PENDING' && $requestResponse->paymentStatus == 'PENDING_CREDIT') {
                    $order->setState('pending');
                    $order->setStatus('pending');
                    $historyComment = 'Alışveriş kredisi başvurusu sürecindedir.';
                    $order->addStatusHistoryComment($historyComment);
                    $order->save();
                    return 'ok';

                }
                if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $requestResponse->status == 'success') {
                    $order->setState('processing');
                    $order->setStatus('processing');
                    $historyComment = 'Alışveriş kredisi işlemi başarıyla tamamlandı.';
                    $order->addStatusHistoryComment($historyComment);
                    $order->save();
                    return 'ok';

                }
                if ($webhookIyziEventType == 'CREDIT_PAYMENT_INIT' && $requestResponse->status == 'INIT_CREDIT') {
                    $order->setState('pending');
                    $order->setStatus('pending');
                    $historyComment = 'Alışveriş kredisi işlemi başlatıldı.';
                    $order->addStatusHistoryComment($historyComment);
                    $order->save();
                    return 'ok';

                }
                if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $requestResponse->status == 'FAILURE') {
                    $order->setState('canceled');
                    $order->setStatus('canceled');
                    $historyComment = 'Alışveriş kredisi işlemi başarısız.';
                    $order->addStatusHistoryComment($historyComment);
                    $order->save();
                    return 'ok';

                }

            }


            if ($webhook != 'webhook' && $requestResponse->paymentStatus == 'PENDING_CREDIT' && $requestResponse->status == 'success') {

                $status = 'PENDING_CREDIT';
            } else {
                $status = $requestResponse->status;
            }

            /* Insert Order Log */
            $iyziOrderModel = $this->_iyziOrderFactory->create([
                'payment_id' => $requestResponse->paymentId,
                'total_amount' => $requestResponse->paidPrice,
                'order_id' => $requestResponse->basketId,
                'status' => $status,
            ]);
            $iyziOrderModel->save();


            /*Bank Transfer */
            if ($requestResponse->paymentStatus == 'INIT_BANK_TRANSFER' && $requestResponse->status == 'success') {
                $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
                $this->_cartManagement->placeOrder($this->_quote->getId());
                $this->_quote->setIyzicoPaymentId($requestResponse->paymentId);

                $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
                return $resultRedirect;
            }

            /* credit shipping */
            if ($webhook != 'webhook' && $requestResponse->paymentStatus == 'PENDING_CREDIT' && $requestResponse->status == 'success') {
                $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
                $this->_quote->setIyziPaymentStatus('PENDING_CREDIT');
                $this->_quote->setIyzicoPaymentId($requestResponse->paymentId);
                $this->_cartManagement->placeOrder($this->_quote->getId());
                $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
                return $resultRedirect;
            }


            /* Error Redirect Start */
            if ($requestResponse->paymentStatus != 'SUCCESS' || $requestResponse->status != 'success') {

                $errorMessage = isset($requestResponse->errorMessage) ? $requestResponse->errorMessage : 'Failed';

                if ($requestResponse->status == 'success' && $requestResponse->paymentStatus == 'FAILURE') {
                    $errorMessage = __('3D Security Error');

                }


                /* Redirect Error */
                $this->_messageManager->addError($errorMessage);
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                return $resultRedirect;

            }


            /* Order ID Confirmation */
            if ($this->_quote->getId() != $requestResponse->basketId && $webhook != 'webhook') {

                $errorMessage = __('Order Not Match');

                /* Redirect Error */
                $this->_messageManager->addError($errorMessage);
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                return $resultRedirect;
            }


            /* Order Price Confirmation */
            $totalPrice = $this->_priceHelper->parsePrice(round($this->_quote->getGrandTotal(), 2));
            if ($totalPrice > $requestResponse->paidPrice) {
                /* Cancel Payment */
                $errorMessage = __('Order Price Not Match');

                /* Redirect Error */
                $this->_messageManager->addError($errorMessage);
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                return $resultRedirect;
            }


            if ($webhook != 'webhook' && $requestResponse->paymentStatus == 'PENDING_CREDIT' && $requestResponse->status == 'success') {

                $this->_quote->setIyziPaymentStatus('PENDING_CREDIT');
                $this->_quote->setIyzicoPaymentId($requestResponse->paymentId);
            } else {
                $this->_quote->setIyziPaymentStatus('success');
            }

            /* Card Save */
            if ($defination['$customerId']) {
                if (isset($requestResponse->cardUserKey)) {
                    $iyziCardFind = $this->_iyziCardFactory->create()->getCollection()
                        ->addFieldToFilter('customer_id', $defination['$customerId'])
                        ->addFieldToFilter('api_key', $defination['apiKey'])
                        ->addFieldToSelect('card_user_key');

                    $iyziCardFind = $iyziCardFind->getData();

                    $defination['customerCardUserKey'] = !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : null;

                    if ($requestResponse->cardUserKey != $defination['customerCardUserKey']) {

                        /* Customer Card Save */
                        $iyziCardModel = $this->_iyziCardFactory->create([
                            'customer_id' => $defination['$customerId'],
                            'card_user_key' => $requestResponse->cardUserKey,
                            'api_key' => $defination['apiKey'],
                        ]);
                        $iyziCardModel->save();
                    }
                }
            }


            $this->_quote->getPayment()->setMethod('iyzipay');
            $installmentFee = 0;


            if (isset($requestResponse->installment) && !empty($requestResponse->installment) && $requestResponse->installment > 1) {

                $installmentFee = $requestResponse->paidPrice - $this->_quote->getGrandTotal();
                $this->_quote->setInstallmentFee($installmentFee);
                $this->_quote->setInstallmentCount($requestResponse->installment);

            }


            /* Set Payment Id */
            $this->_quote->setIyzicoPaymentId($requestResponse->paymentId);

            if ($webhook == 'webhook' && $requestResponse->status == 'success' && $requestResponse->paymentStatus == 'SUCCESS') {

                try {
                    $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
                    $this->_quote->setCustomerEmail($this->_customerSession->getEmail());
                    $this->_cartManagement->placeOrder($requestResponse->basketId);
                    return $webhookHelper->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
                } catch (Exception $e) {
                    return $webhookHelper->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
                }

            }

            if ($this->_customerSession->isLoggedIn()) {
                /* Place Order - Login Checkout */
                $this->_cartManagement->placeOrder($this->_quote->getId());

            } else {

                $this->_quote->setCheckoutMethod($this->_cartManagement::METHOD_GUEST);
                $this->_quote->setCustomerEmail($this->_customerSession->getEmail());
                $this->_cartManagement->placeOrder($this->_quote->getId());

            }


            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
            return $resultRedirect;


        } catch (Exception $e) {
            if ($webhook == 'webhook') {
                return $webhookHelper->webhookHttpResponse($requestResponse->errorCode . '-' . $requestResponse->errorMessage, 404);
            }
            /* Redirect Error */
            $this->_messageManager->addError($e);
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
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
}
