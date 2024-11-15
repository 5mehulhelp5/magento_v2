<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Quote\Model\Quote\Item;

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
     * Calculate Subtotal Price
     *
     * @param  Item|array  $order
     * @return string
     */
    public function calculateSubTotalPrice(Item|array $order): string
    {
        $price = 0;
        foreach ($order->getAllVisibleItems() as $item) {
            $price += round($item->getPrice(), 2);
        }

        $price += $order->getShippingAddress()->getShippingAmount() ?? 0;
        return $this->parsePrice($price);
    }

    /**
     * Trailing Zero
     *
     * @param  float  $price
     * @return string
     */
    public function trailingZero(float $price): string
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
     * @param  string  $name  Name of the cookie
     * @param  string  $value  Value of the cookie
     * @param  int  $expire  Expiration time
     * @param  string  $domain  Domain for the cookie
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
     * Extract Locale
     *
     * @param  string  $locale
     * @return string
     */
    public function extractLocale(string $locale): string
    {
        $localeParts = explode('_', $locale);
        return $localeParts[0];
    }

    /**
     * Concatenate Strings
     *
     * @param  string  ...$address
     * @return string
     */
    public function concatenateStrings(string ...$address): string
    {
        $address = array_map('trim', $address);
        return implode(' ', $address);
    }

    /**
     * Validate String
     *
     * @param  mixed  $string
     * @return string
     */
    public function validateString(mixed $string): string
    {
        if (!empty(trim($string))) {
            return $string;
        }

        return "NOT PROVIDED";
    }

    /**
     * Generate Conversation Id
     *
     * @param  int  $quoteId
     * @return string
     */
    public function generateConversationId(int $quoteId): string
    {
        return 'QI'.$quoteId.'T'.time();
    }

}