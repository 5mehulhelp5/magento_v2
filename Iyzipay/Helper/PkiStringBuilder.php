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

		$pki_value = "[";
		foreach ($objectData as $key => $data) {
			if(is_object($data)) {
				$name = var_export($key, true);
				$name = str_replace("'", "", $name);
				$pki_value .= $name."=[";
				$end_key = count(get_object_vars($data));
				$count 	 = 0;
				foreach ($data as $key => $value) {
					$count++;
					$name = var_export($key, true);
					$name = str_replace("'", "", $name);
					$pki_value .= $name."="."".$value;
					if($end_key != $count)
						$pki_value .= ",";
				}
				$pki_value .= "]";
			} else if(is_array($data)) {
				$name = var_export($key, true);
				$name = str_replace("'", "", $name);
				$pki_value .= $name."=[";
				$end_key = count($data);
				$count 	 = 0;
				foreach ($data as $key => $result) {
					$count++;
					$pki_value .= "[";

					foreach ($result as $key => $item) {
						$name = var_export($key, true);
						$name = str_replace("'", "", $name);

						$pki_value .= $name."="."".$item;
						$reResult = (array) $result;
            $newResult = $reResult[array_key_last($reResult)];

						if($newResult != $item) {
							$pki_value .= ",";
						}

						if($newResult == $item) {

							if($end_key != $count) {
								$pki_value .= "], ";

							} else {
								$pki_value .= "]";
							}
						}
					}
				}

				$reData = (array) $data;
        $newData = $reData[array_key_last($reData)];
				if($newData == $result)
					$pki_value .= "]";

			} else {
				$name = var_export($key, true);
				$name = str_replace("'", "", $name);

				$pki_value .= $name."="."".$data."";
			}

				$reObjectData = (array)$objectData;
        $newobjectData = $reObjectData[array_key_last($reObjectData)];

			if($newobjectData != $data)
				$pki_value .= ",";
		}
		$pki_value .= "]";
		return $pki_value;
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
