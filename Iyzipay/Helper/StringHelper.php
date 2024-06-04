<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Tests\NamingConvention\true\string;

class StringHelper
{
    /**
     * TODO: cutLocale (yeni: extractLocale), trimString (yeni: concatenateStrings), dataCheck (yeni: validateString) gibi fonksiyonlar buraya taşınacak
     */

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

    public function validateString(string $string): string
    {

        if (!empty(trim($string))) {
            return $string;
        }

        return "NOT PROVIDED";
    }
}
