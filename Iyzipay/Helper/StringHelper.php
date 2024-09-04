<?php

namespace Iyzico\Iyzipay\Helper;

/**
 * Class StringHelper
 *
 * This class is used to handle string operations
 *
 * @package Iyzico\Iyzipay\Helper
 */
class StringHelper
{

    /**
     * Extracts the locale from the given locale string
     *
     * @param string $locale
     * @return string
     */
    public function extractLocale(string $locale): string
    {
        $localeParts = explode('_', $locale);
        return $localeParts[0];
    }

    /**
     * Trims the given string and concatenates them with a space
     *
     * @param string ...$address
     * @return string
     */
    public function concatenateStrings(string ...$address): string
    {
        $address = array_map('trim', $address);
        return implode(' ', $address);
    }

    /**
     * Validates the given string
     *
     * @param mixed $string
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
     * @param mixed $quoteId
     * @return string
     */
    public function generateConversationId(mixed $quoteId): string
    {
        return 'QI' . $quoteId . 'T' . time();
    }
}
