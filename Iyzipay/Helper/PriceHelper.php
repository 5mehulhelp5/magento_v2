<?php

namespace Iyzico\Iyzipay\Helper;

class PriceHelper
{
    /**
     * TODO: subTotalPriceCalc (yeni: calculateSubtotalPrice), priceParser (yeni: parsePrice) gibi fonksiyonlar buraya taşınacak
     */

    public function calculateSubtotalPrice($checkout)
    {
        $price = 0;

        foreach ($checkout->getAllVisibleItems() as $item) {
            $price += round($item->getPrice(), 2);
        }

        $price += $checkout->getShippingAddress()->getShippingAmount() ?? 0;

        return $this->parsePrice($price);
    }

    public function parsePrice($price)
    {
        if (!str_contains($price, ".")) {
            return $price . ".0";
        }

        $priceReversed = strrev($price);
        $subStrIndex = strcspn($priceReversed, "1-9.") + 1;

        if (!str_contains($priceReversed, ".")) {
            $priceReversed = "0" . $priceReversed;
        }

        return strrev(substr($priceReversed, $subStrIndex));
    }

}
