<?php

namespace Iyzico\Iyzipay\Model;

use Iyzico\Iyzipay\Api\WebhookInterface;
use Iyzico\Iyzipay\Helper\ConfigHelper;
use Iyzico\Iyzipay\Helper\UtilityHelper;
use Iyzico\Iyzipay\Model\Data\WebhookData;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;

class Webhook implements WebhookInterface
{
    protected string $signatureV3;
    protected WebhookData $webhookData;

    public function __construct(
        protected RequestInterface $request,
        protected ConfigHelper $configHelper,
        protected UtilityHelper $utilityHelper
    ) {
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function check(string $webhookUrlKey): void
    {
        if ($webhookUrlKey !== $this->configHelper->getWebhookUrlKey()) {
            throw new NotFoundException(__('Webhook URL key not found.'), null, 404);
        }

        $this->signatureV3 = $this->getWebhookHeader();
        $this->webhookData = $this->getWebhookBody();

        $secretKey = $this->configHelper->getSecretKey();
        $key = $this->generateKey($secretKey, $this->webhookData);

        $hmac256Signature = bin2hex(hash_hmac('sha256', $key, $secretKey, true));
        $signatureMatchStatus = $this->validateSignature($this->signatureV3, $hmac256Signature);

        if (!$signatureMatchStatus) {
            echo "Bu kısımda iyzico ödeme durumu sorgulama işlemi yapılacak";
        } else {
            echo "Bu kısımda ödeme durumu güncelleme işlemi yapılacak";
        }
    }

    /**
     * @inheritDoc
     */
    public function getWebhookHeader(): string
    {
        return $this->request->getHeader('X-Iyz-Signature-V3');
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function getWebhookBody(): WebhookData
    {
        $webhookData = new WebhookData();

        $content = $this->request->getContent();
        if (empty($content)) {
            throw new LocalizedException(__('Request body is empty'), null, 400);
        }

        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(
                __('Invalid JSON: %1', json_last_error_msg())
            );
        }

        $paymentConversationId = $json['paymentConversationId'] ?? null;
        if (empty($paymentConversationId)) {
            throw new LocalizedException(__('paymentConversationId is missing or empty'));
        }

        $merchantId = $json['merchantId'] ?? null;
        if (empty($merchantId)) {
            throw new LocalizedException(__('merchantId is missing or empty'));
        }

        $token = $json['token'] ?? null;
        if (empty($token)) {
            throw new LocalizedException(__('token is missing or empty'));
        }

        $status = $json['status'] ?? null;
        if (empty($status)) {
            throw new LocalizedException(__('status is missing or empty'));
        }

        $iyziReferenceCode = $json['iyziReferenceCode'] ?? null;
        if (empty($iyziReferenceCode)) {
            throw new LocalizedException(__('iyziReferenceCode is missing or empty'));
        }

        $iyziEventType = $json['iyziEventType'] ?? null;
        if (empty($iyziEventType)) {
            throw new LocalizedException(__('iyziEventType is missing or empty'));
        }

        $iyziEventTime = $json['iyziEventTime'] ?? null;
        if (empty($iyziEventTime)) {
            throw new LocalizedException(__('iyziEventTime is missing or empty'));
        }

        $iyziPaymentId = $json['iyziPaymentId'] ?? null;
        if (empty($iyziPaymentId)) {
            throw new LocalizedException(__('iyziPaymentId is missing or empty'));
        }


        $webhookData->setPaymentConversationId(strip_tags((string) $paymentConversationId));
        $webhookData->setMerchantId((int) $merchantId);
        $webhookData->setToken(strip_tags((string) $token));
        $webhookData->setStatus(strip_tags((string) $status));
        $webhookData->setIyziReferenceCode(strip_tags((string) $iyziReferenceCode));
        $webhookData->setIyziEventType(strip_tags((string) $iyziEventType));
        $webhookData->setIyziEventTime((int) $iyziEventTime);
        $webhookData->setIyziPaymentId((int) $iyziPaymentId);

        return $webhookData;
    }

    public function generateKey(string $secretKey, WebhookData $webhookData): string
    {
        return $secretKey.$webhookData->getIyziEventType().$webhookData->getIyziPaymentId().$webhookData->getPaymentConversationId().$webhookData->getStatus();
    }

    /**
     * @inheritDoc
     */
    public function validateSignature(string $signature, string $payload): bool
    {
        return hash_equals($signature, $payload);
    }

    /**
     * @inheritDoc
     */
    public function processWebhook(array $data): mixed
    {
        // TODO: Implement processWebhook() method.
        return null;
    }

    /**
     * @inheritDoc
     */
    public function processWebhookV3(array $data): mixed
    {
        // TODO: Implement processWebhookV3() method.
        return null;
    }

    /**
     * @inheritDoc
     */
    public function logWebhookEvent(string $eventType, array $data, string $status): void
    {
        // TODO: Implement logWebhookEvent() method.
    }
}
