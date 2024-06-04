<?php

namespace Iyzico\Iyzipay\Helper;

use stdClass;

class PkiStringBuilder
{
    /**
     * TODO: pkiStringGenerate (yeni: generatePkiString), createFormObjectSort (yeni: sortFormObject), authorizationGenerate (yeni: generateAuthorization)
     */

    public function generatePkiString($objectData): string
    {
        $pkiValue = "[";

        $keys = array_keys((array)$objectData);
        $lastKey = end($keys);

        foreach ($objectData as $key => $data) {
            $name = str_replace("'", "", var_export($key, true));

            if (is_object($data)) {
                $pkiValue .= $this->handleObject($data, $name);
            } elseif (is_array($data)) {
                $pkiValue .= $this->handleArray($data, $name);
            } else {
                $pkiValue .= $name . "=" . $data;
            }

            if ($key !== $lastKey) {
                $pkiValue .= ",";
            }
        }

        $pkiValue .= "]";
        return $pkiValue;
    }

    private function handleObject($data, $name)
    {
        $pkiValue = $name . "=[";
        $objectVars = (array)$data;
        $keys = array_keys($objectVars);
        $lastKey = end($keys);

        foreach ($objectVars as $key => $value) {
            $name = str_replace("'", "", var_export($key, true));
            $pkiValue .= $name . "=" . $value;

            if ($key !== $lastKey) {
                $pkiValue .= ",";
            }
        }

        $pkiValue .= "]";
        return $pkiValue;
    }

    private function handleArray($data, $name)
    {
        $pkiValue = $name . "=[";
        $keys = array_keys($data);
        $lastKey = end($keys);

        foreach ($data as $key => $result) {
            $pkiValue .= "[";

            $resultVars = (array)$result;
            $resultKeys = array_keys($resultVars);
            $lastResultKey = end($resultKeys);

            foreach ($resultVars as $key => $item) {
                $name = str_replace("'", "", var_export($key, true));
                $pkiValue .= $name . "=" . $item;

                if ($key !== $lastResultKey) {
                    $pkiValue .= ",";
                }
            }

            if ($key !== $lastKey) {
                $pkiValue .= "], ";
            } else {
                $pkiValue .= "]";
            }
        }

        $pkiValue .= "]";
        return $pkiValue;
    }

    public function sortFormObject($objectData): stdClass
    {
        $formObject = new stdClass();

        $formObject->locale = $objectData->locale;
        $formObject->conversationId = $objectData->conversationId;
        $formObject->price = $objectData->price;
        $formObject->basketId = $objectData->basketId;
        $formObject->paymentGroup = $objectData->paymentGroup;

        $formObject->buyer = $objectData->buyer;
        $formObject->shippingAddress = $objectData->shippingAddress;
        $formObject->billingAddress = $objectData->billingAddress;

        foreach ($objectData->basketItems as $key => $item) {
            $formObject->basketItems[$key] = $item;
        }

        $formObject->callbackUrl = $objectData->callbackUrl;
        $formObject->paymentSource = $objectData->paymentSource;
        $formObject->currency = $objectData->currency;
        $formObject->paidPrice = $objectData->paidPrice;
        $formObject->forceThreeDS = $objectData->forceThreeDS;
        $formObject->cardUserKey = $objectData->cardUserKey;
        $formObject->goBackUrl = $objectData->goBackUrl;

        return $formObject;
    }

    public function generateAuthorization($pkiString, $apiKey, $secretKey, $rand): array
    {

        $hash_value = $apiKey . $rand . $secretKey . $pkiString;
        $hash = base64_encode(sha1($hash_value, true));

        $authorization = 'IYZWS ' . $apiKey . ':' . $hash;

        return array(
            'authorization' => $authorization,
            'rand_value' => $rand
        );
    }


}
