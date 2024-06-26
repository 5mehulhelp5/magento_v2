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

namespace Iyzico\Iyzipay\Cron;


use Iyzico\Iyzipay\Helper\ResponseObjectHelper;
use Iyzico\Iyzipay\Helper\PkiStringBuilder;
use Iyzico\Iyzipay\Helper\PkiStringBuilderFactory;
use Iyzico\Iyzipay\Logger\IyziCronLogger;
use Iyzico\Iyzipay\Model\ResourceModel\IyziOrderJob\Collection;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;


use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;


class ProcessPendingOrders
{
    protected IyziCronLogger $cronLogger;
    protected Collection $collection;
    protected ResponseObjectHelper $responseObjectHelper;
    protected ScopeConfigInterface $scopeConfig;
    protected PkiStringBuilderFactory $pkiStringBuilderFactory;
    protected OrderRepositoryInterface $orderRepository;


    public function __construct(
        IyziCronLogger $cronLogger,
        Collection $collection,
        ResponseObjectHelper $responseObjectHelper,
        ScopeConfigInterface $scopeConfig,
        PkiStringBuilderFactory $pkiStringBuilderFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->cronLogger = $cronLogger;
        $this->collection = $collection;
        $this->responseObjectHelper = $responseObjectHelper;
        $this->scopeConfig = $scopeConfig;
        $this->pkiStringBuilderFactory = $pkiStringBuilderFactory;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $_orders = $this->all();
        $promises = [];
        $client = new Client();

        foreach ($_orders as $order) {
            $token = $order['iyzico_payment_token'];
            $conversationId = $order['iyzico_conversationId'];
            $promises[$order['id']] = $this->getPaymentDetailAsync($client, $token, $conversationId);
        }

        $responses = Utils::settle($promises)->wait();

        foreach ($_orders as $order) {
            $response = $responses[$order['id']]['value'];
            $responseBody = json_decode($response->getBody());

            $conversationId = $order['iyzico_conversationId'];
            $responseConversationId = $responseBody->conversationId ?? '0';

            if (!$this->validateConversationId($conversationId, $responseConversationId)) {
                continue;
            }

            $order = $this->collection->addFieldToFilter('id', $order['id'])->getFirstItem();

            $this->updateOrder($order, $responseBody);
            $this->updateLastControlDate($order);
            $this->updateControlled($order);
        }

        return [];
    }

    private function all()
    {
        $this->collection->addFieldToFilter('status', ['in' => ['pending_payment', 'received']]);
        $this->collection->addFieldToFilter('is_controlled', ['eq' => 0]);

        foreach ($this->collection as $order) {
            $array[] = $order->getData();
        }

        return $array ?? [];
    }

    private function updateControlled($order)
    {
        $order->getStatus() == 'canceled' ? $order->setIsControlled(1) : $order->setIsControlled(0);
        $order->getStatus() == 'processing' ? $order->setIsControlled(1) : $order->setIsControlled(0);
        $order->save();
    }

    private function updateLastControlDate($order)
    {
        $oldLastControlledAt = $order->getLastControlledAt();
        $newLastControlledAt = date('Y-m-d H:i:s');

        $order->setLastControlledAt($newLastControlledAt);

        $this->cronLogger->info('Last control date updated', [
            'order_id' => $order->getOrderId(),
            'old_last_control_date' => $oldLastControlledAt,
            'new_last_control_date' => $newLastControlledAt
        ]);

        $order->save();
    }

    private function validateConversationId(string $conversationId, string $responseConversationId)
    {
        if ($conversationId !== $responseConversationId) {
            $this->cronLogger->error('Conversation ID does not match', [
                'conversation_id' => $conversationId,
                'response_conversation_id' => $responseConversationId
            ]);

            return false;
        }

        return true;
    }

    private function updateOrder($order, $responseBody)
    {
        $paymentStatus = $responseBody->paymentStatus ?? '';
        $status = $responseBody->status ?? '';

        $mapping = $this->mapping($paymentStatus, $status);

        if (empty($mapping)) {
            $this->cronLogger->error('Mapping not found', [
                'payment_status' => $paymentStatus,
                'status' => $status
            ]);

            return;
        }

        $order->setStatus($mapping['status']);

        $magentoOrder = $this->orderRepository->get($order->getOrderId());
        $magentoOrder->setState($mapping['state']);
        $magentoOrder->setStatus($mapping['status']);
        $magentoOrder->addStatusHistoryComment($mapping['comment']);

        $this->cronLogger->info('Order status updated', [
            'order_id' => $order->getOrderId(),
            'state' => $mapping['state'],
            'status' => $mapping['status'],
            'comment' => $mapping['comment']
        ]);

        $order->save();
        $magentoOrder->save();
    }

    private function mapping(string $paymentStatus, string $status): array
    {
        if ($status == "failure")
            return ['state' => "canceled", 'status' => "canceled", 'comment' => __("CANCELLED_ORDER")];

        if ($paymentStatus == 'INIT_THREEDS' && $status == 'success')
            return ['state' => "pending_payment", 'status' => "pending_payment", 'comment' => __("INIT_THREEDS_CRON")];

        if ($paymentStatus == 'SUCCESS' && $status == 'success')
            return ['state' => "processing", 'status' => "processing", 'comment' => __("SUCCESS")];

        if ($paymentStatus == 'INIT_BANK_TRANSFER' && $status == 'success')
            return ['state' => "pending_payment", 'status' => "pending_payment", 'comment' => __("INIT_BANK_TRANSFER")];

        if ($paymentStatus == 'PENDING_CREDIT' && $status == 'success')
            return ['state' => "pending_payment", 'status' => "pending_payment", 'comment' => __("PENDING_CREDIT")];

        return [];
    }

    /**
     * Retrieve Payment Detail Asynchronously
     *
     * This function is responsible for retrieving the payment detail asynchronously.
     *
     * @param Client $client
     * @param string $token
     * @param string $conversationId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function getPaymentDetailAsync(Client $client, string $token, string $conversationId)
    {
        $defination = $this->getPaymentDefinition();
        $pkiStringBuilder = $this->getPkiStringBuilder();

        $tokenDetailObject = $this->responseObjectHelper->createTokenDetailObject($conversationId, $token);
        $iyzicoPkiString = $pkiStringBuilder->generatePkiString($tokenDetailObject);
        $authorization = $pkiStringBuilder->generateAuthorization($iyzicoPkiString, $defination['apiKey'], $defination['secretKey'], $defination['rand']);
        $iyzicoJson = json_encode($tokenDetailObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = $defination['baseUrl'] . '/payment/iyzipos/checkoutform/auth/ecom/detail';

        return $client->postAsync($url, [
            'body' => $iyzicoJson,
            'headers' => [
                'Authorization' => $authorization['authorization'],
                'x-iyzi-rnd' => $authorization['rand_value'],
                'Content-Type' => 'application/json',
            ],
        ]);
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
            'baseUrl' => $this->scopeConfig->getValue('payment/iyzipay/sandbox') ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
            'apiKey' => $this->scopeConfig->getValue('payment/iyzipay/api_key'),
            'secretKey' => $this->scopeConfig->getValue('payment/iyzipay/secret_key')
        ];
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


}

// Sipariş durumu pending_payment veya received olan siparişler kontrol edilmeli.
// is_controlled olarak işaretlenmeli.
// table name: iyzi_order_job
