<?php

namespace Iyzico\Iyzipay\Helper;

class PriceHelper
{
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
        if (strpos($price, ".") === false) {
            return $price . ".0";
        }

        $subStrIndex = 0;
        $priceReversed = strrev($price);
        for ($i = 0; $i < strlen($priceReversed); $i++) {
            if (strcmp($priceReversed[$i], "0") == 0) {
                $subStrIndex = $i + 1;
            } else if (strcmp($priceReversed[$i], ".") == 0) {
                $priceReversed = "0" . $priceReversed;
                break;
            } else {
                break;
            }
        }

        return strrev(substr($priceReversed, $subStrIndex));
    }

    public function calculateInstallmentPrice($paidPrice, $grandTotal)
    {
        return $this->parsePrice($paidPrice - $grandTotal);
    }

}
