<?php

namespace Iyzico\Iyzipay\Helper;

use Iyzico\Iyzipay\Library\Model\CheckoutForm;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

class UtilityHelper
{
    private const COOKIE_EXPIRE_TIME = 86400;

    /**
     * Calculate Installment Price
     *
     * @param  float  $paidPrice
     * @param  float  $grandTotal
     * @return string
     */
    public function calculateInstallmentPrice(float $paidPrice, float $grandTotal): string
    {
        return $this->parsePrice($paidPrice - $grandTotal);
    }

    /**
     * Trailing Zero
     *
     * @param  float  $price
     * @return string
     */
    public function parsePrice(float $price): string
    {
        if (strpos($price, ".") === false) {
            return $price.".0";
        }

        $subStrIndex = 0;
        $priceReversed = strrev($price);
        for ($i = 0; $i < strlen($priceReversed); $i++) {
            if (strcmp($priceReversed[$i], "0") == 0) {
                $subStrIndex = $i + 1;
            } else {
                if (strcmp($priceReversed[$i], ".") == 0) {
                    $priceReversed = "0".$priceReversed;
                    break;
                } else {
                    break;
                }
            }
        }

        return strrev(substr($priceReversed, $subStrIndex));
    }

    /**
     * Ensure Cookies Same Site
     *
     * Sets SameSite=None; Secure on specified cookies.
     *
     * @return void
     */
    public function ensureCookiesSameSite(): void
    {
        $cookieNamesToCheck = [
            'PHPSESSID',
            'adminhtml',
            'frontend',
            'mage-cache-sessid',
            'mage-cache-storage',
            'mage-cache-storage-section-invalidation',
            'mage-messages',
            'mage-translation-file-version',
            'mage-translation-storage'
        ];

        $cookieNamesToCheck = array_flip($cookieNamesToCheck);

        foreach ($_COOKIE as $cookieName => $value) {
            if (isset($cookieNamesToCheck[$cookieName])) {
                $this->applyCookieSettings(
                    $cookieName,
                    $value,
                    time() + self::COOKIE_EXPIRE_TIME,
                    $_SERVER['SERVER_NAME']
                );
            }
        }
    }

    /**
     * Apply Cookie Settings
     *
     * This function is responsible for applying the cookie settings.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int  $expire
     * @param  string  $domain
     *
     * @return void
     */
    private function applyCookieSettings(string $name, string $value, int $expire, string $domain): void
    {
        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, "/; samesite=None", $domain, true, true);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => "/",
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => true,
                'httponly' => true
            ]);
        }
    }

    /**
     * Validate String
     *
     * This function is responsible for validating the string.
     *
     * @param  mixed  $string
     * @return string
     */
    public function validateString(mixed $string): string
    {
        if (is_array($string)) {
            return implode(' ', $string);
        }

        if (!empty(trim($string))) {
            return $string;
        }

        return "NOT PROVIDED";
    }

    /**
     * Generate Conversation Id
     *
     * This function is responsible for generating the conversation ID.
     *
     * @param  int  $quoteId
     * @return string
     */
    public function generateConversationId(int $quoteId): string
    {
        return 'QI'.$quoteId.'T'.time();
    }

    /**
     * Get Customer Id
     *
     * This function is responsible for getting the customer ID.
     *
     * @param  CustomerSession  $customerSession
     * @return int|null
     */
    public function getCustomerId(CustomerSession $customerSession): ?int
    {
        return $customerSession->isLoggedIn() ? $customerSession->getCustomerId() : 0;
    }

    /**
     * Get Customer Card User Key
     *
     * This function is responsible for getting the customer card user key.
     *
     * @param  IyziCardFactory  $iyziCardFactory
     * @param  int  $customerId
     * @param  string  $apiKey
     * @return string
     */
    public function getCustomerCardUserKey(IyziCardFactory $iyziCardFactory, int $customerId, string $apiKey): string
    {
        if ($customerId) {
            $iyziCardFind = $iyziCardFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('api_key', $apiKey)
                ->addFieldToSelect('card_user_key');
            $iyziCardFind = $iyziCardFind->getData();
            return !empty($iyziCardFind[0]['card_user_key']) ? $iyziCardFind[0]['card_user_key'] : '';
        }
        return '';
    }

    /**
     * Get Locale Name
     *
     * This function is responsible for getting the locale name.
     *
     * @param $locale
     * @return string
     */
    public function cutLocale($locale): string
    {
        $locale = explode('_', $locale);
        return $locale[0];
    }

    /**
     * Store Session Data
     *
     * This function is responsible for storing the session data.
     *
     * @param  Quote  $checkoutSession
     * @param  CustomerSession  $customerSession
     * @return void
     */
    public function storeSessionData(Quote $checkoutSession, CustomerSession $customerSession): void
    {
        $customerEmail = $checkoutSession->getBillingAddress()->getEmail();
        $quoteId = $checkoutSession->getId();
        $checkoutSession->setGuestQuoteId($quoteId);
        $customerSession->setEmail($customerEmail);
    }

    /**
     * Validate Signature
     *
     * This function is responsible for validating the signature.
     *
     * @param  CheckoutForm  $response
     * @param  string  $secretKey
     * @throws LocalizedException
     */
    public function validateSignature(CheckoutForm $response, string $secretKey): void
    {
        $responsePaymentStatus = $response->getPaymentStatus();
        $responsePaymentId = $response->getPaymentId();
        $responseCurrency = $response->getCurrency();
        $responseBasketId = $response->getBasketId();
        $responseConversationId = $response->getConversationId();
        $responsePaidPrice = $response->getPaidPrice();
        $responsePrice = $response->getPrice();
        $responseToken = $response->getToken();
        $responseSignature = $response->getSignature();

        $calculateSignature = $this->calculateHmacSHA256Signature([
            $responsePaymentStatus,
            $responsePaymentId,
            $responseCurrency,
            $responseBasketId,
            $responseConversationId,
            $responsePaidPrice,
            $responsePrice,
            $responseToken
        ], $secretKey);

        if ($responseSignature !== $calculateSignature) {
            throw new LocalizedException(__('Signature mismatch'));
        }
    }

    /**
     * Calculate HMAC SHA256 Signature
     *
     * This function is responsible for calculating the HMAC SHA256 signature.
     *
     * @param  array  $params
     * @param  string  $secretKey
     * @return string
     */
    public function calculateHmacSHA256Signature(array $params, string $secretKey): string
    {
        $dataToSign = implode(':', $params);
        $mac = hash_hmac('sha256', $dataToSign, $secretKey, true);

        return bin2hex($mac);
    }

    /**
     * Validate Conversation ID
     *
     * This function is responsible for validating the conversation ID.
     *
     * @param  string  $conversationId
     * @param  string  $responseConversationId
     * @return bool
     */
    public function validateConversationId(string $conversationId, string $responseConversationId): bool
    {
        if ($conversationId !== $responseConversationId) {
            return false;
        }
        return true;
    }

    /**
     * Find Order By State And Status
     *
     * This function is responsible for finding the order by state and status.
     *
     * @param  string  $responsePaymentStatus
     * @param  string  $responseStatus
     * @return array
     */
    public function findOrderByPaymentAndStatus(string $responsePaymentStatus, string $responseStatus): array
    {
        $ordersByPaymentAndStatus = [
            'state' => '',
            'status' => '',
            'comment' => '',
            'orderJobStatus' => ''
        ];

        if ($responsePaymentStatus == 'PENDING_CREDIT' && $responseStatus == 'success') {
            $ordersByPaymentAndStatus['state'] = 'pending_payment';
            $ordersByPaymentAndStatus['status'] = 'pending_payment';
            $ordersByPaymentAndStatus['comment'] = __('PENDING_CREDIT');
            $ordersByPaymentAndStatus['orderJobStatus'] = 'pending_payment';
        }

        if ($responsePaymentStatus == 'INIT_BANK_TRANSFER' && $responseStatus == 'success') {
            $ordersByPaymentAndStatus['state'] = 'pending_payment';
            $ordersByPaymentAndStatus['status'] = 'pending_payment';
            $ordersByPaymentAndStatus['comment'] = __('INIT_BANK_TRANSFER');
            $ordersByPaymentAndStatus['orderJobStatus'] = 'pending_payment';
        }

        if ($responsePaymentStatus == 'INIT_THREEDS' && $responseStatus == 'success') {
            $ordersByPaymentAndStatus['state'] = 'pending_payment';
            $ordersByPaymentAndStatus['status'] = 'pending_payment';
            $ordersByPaymentAndStatus['comment'] = __('INIT_THREEDS_CRON');
            $ordersByPaymentAndStatus['orderJobStatus'] = 'pending_payment';
        }

        if ($responsePaymentStatus == 'SUCCESS' && $responseStatus == 'success') {
            $ordersByPaymentAndStatus['state'] = 'processing';
            $ordersByPaymentAndStatus['status'] = 'processing';
            $ordersByPaymentAndStatus['comment'] = __('SUCCESS');
            $ordersByPaymentAndStatus['orderJobStatus'] = 'processing';
        }

        if ($responsePaymentStatus == 'FAILURE') {
            $ordersByPaymentAndStatus['state'] = 'canceled';
            $ordersByPaymentAndStatus['status'] = 'canceled';
            $ordersByPaymentAndStatus['comment'] = __('FAILURE');
            $ordersByPaymentAndStatus['orderJobStatus'] = 'canceled';
        }

        return $ordersByPaymentAndStatus;
    }
}
