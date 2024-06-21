<?php

namespace Iyzico\Iyzipay\Helper;

use stdClass;

class ObjectHelper
{
    private $stringHelper;
    private $priceHelper;

    public function __construct(StringHelper $stringHelper, PriceHelper $priceHelper)
    {
        $this->stringHelper = $stringHelper;
        $this->priceHelper = $priceHelper;
    }

    public function createPaymentOption($checkoutSession, $cardUserKey, $locale, $conversationId, $currency, $quoteId, $callBack, $magentoVersion): stdClass
    {
        $object = new stdClass();

        $object->locale = $this->stringHelper->extractLocale($locale);
        $object->conversationId = $conversationId;
        $object->price = $this->priceHelper->calculateSubtotalPrice($checkoutSession);
        $object->paidPrice = $this->priceHelper->parsePrice(round($checkoutSession->getGrandTotal(), 2));
        $object->currency = $currency;
        $object->basketId = $quoteId;
        $object->paymentGroup = 'PRODUCT';
        $object->forceThreeDS = "0";
        $object->callbackUrl = $callBack . "Iyzico_Iyzipay/response/iyzicocheckoutform";
        $object->cardUserKey = $cardUserKey;
        $object->paymentSource = "MAGENTO2|" . $magentoVersion . "|SPACE-2.0.0";
        $object->goBackUrl = $callBack;

        return $object;
    }

    public function createBuyerObject($checkoutSession, $guestEmail): stdClass
    {
        $billingAddress = $checkoutSession->getBillingAddress();
        $billingStreet = $this->stringHelper->concatenateStrings(...$billingAddress->getStreet());

        $email = $billingAddress->getEmail() ?: $guestEmail;

        $object = new stdClass();

        $object->id = $billingAddress->getId();
        $object->name = $this->stringHelper->validateString($billingAddress->getName());
        $object->surname = $this->stringHelper->validateString($billingAddress->getName());
        $object->identityNumber = "11111111111";
        $object->email = $this->stringHelper->validateString($email);
        $object->gsmNumber = $this->stringHelper->validateString($billingAddress->getTelephone());
        $object->registrationDate = "2018-07-06 11:11:11";
        $object->lastLoginDate = "2018-07-06 11:11:11";
        $object->registrationAddress = $this->stringHelper->validateString($billingStreet);
        $object->city = $this->stringHelper->validateString($billingAddress->getCity());
        $object->country = $this->stringHelper->validateString($billingAddress->getCountry());
        $object->zipCode = $this->stringHelper->validateString($billingAddress->getPostCode());
        $object->ip = $_SERVER['REMOTE_ADDR'];

        return $object;
    }

    public function createShippingAddressObject($checkoutSession)
    {
        $shippingAddress = $checkoutSession->getShippingAddress();
        $shippingStreet = $this->stringHelper->concatenateStrings(...$shippingAddress->getStreet());

        $object = new stdClass();

        $object->address = $this->stringHelper->validateString($shippingStreet);
        $object->zipCode = $this->stringHelper->validateString($shippingAddress->getPostCode());
        $object->contactName = $this->stringHelper->validateString($shippingAddress->getName());
        $object->city = $this->stringHelper->validateString($shippingAddress->getCity());
        $object->country = $this->stringHelper->validateString($shippingAddress->getCountry());

        return $object;
    }

    public function createBillingAddressObject($checkoutSession)
    {
        $billingAddress = $checkoutSession->getBillingAddress();
        $billingStreet = $this->stringHelper->concatenateStrings(...$billingAddress->getStreet());

        $object = new stdClass();

        $object->address = $this->stringHelper->validateString($billingStreet);
        $object->zipCode = $this->stringHelper->validateString($billingAddress->getPostCode());
        $object->contactName = $this->stringHelper->validateString($billingAddress->getName());
        $object->city = $this->stringHelper->validateString($billingAddress->getCity());
        $object->country = $this->stringHelper->validateString($billingAddress->getCountry());

        return $object;
    }

    public function createBasketItems($checkoutSession)
    {
        $basketItems = $checkoutSession->getAllVisibleItems();
        $keyNumber = 0;

        /* Basket Items */
        foreach ($basketItems as $key => $item) {
            $basketItems[$keyNumber] = new stdClass();

            $basketItems[$keyNumber]->id = $item->getProductId();
            $basketItems[$keyNumber]->price = $this->priceHelper->parsePrice(round($item->getPrice(), 2));
            $basketItems[$keyNumber]->name = $this->stringHelper->validateString($item->getName());
            $basketItems[$keyNumber]->category1 = $this->stringHelper->validateString($item->getName());
            $basketItems[$keyNumber]->itemType = "PHYSICAL";

            $keyNumber++;
        }

        $shipping = $checkoutSession->getShippingAddress()->getShippingAmount();

        if ($shipping && $shipping != '0' && $shipping != '0.0' && $shipping != '0.00') {
            $endKey = count($basketItems);

            $basketItems[$endKey] = new stdClass();

            $basketItems[$endKey]->id = (string) rand();
            $basketItems[$endKey]->price = $this->priceHelper->parsePrice($shipping);
            $basketItems[$endKey]->name = "Cargo";
            $basketItems[$endKey]->category1 = "Cargo";
            $basketItems[$endKey]->itemType = "PHYSICAL";
        }

        return $basketItems;
    }
}
