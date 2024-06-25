<?php

namespace Iyzico\Iyzipay\Helper;

class RequestHelper
{
    public function sendCheckoutFormRequest($baseUrl, $json, $authorizationData)
    {
        $url = $baseUrl . '/payment/iyzipos/checkoutform/initialize/auth/ecom';
        return $this->sendCurlPostRequest($json, $authorizationData, $url);
    }

    private function sendCurlPostRequest($json, $authorizationData, $url)
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);

        if ($json) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 150);

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: " . $authorizationData['authorization'],
                "x-iyzi-rnd:" . $authorizationData['rand_value'],
                "Content-Type: application/json",
            )
        );

        $result = json_decode(curl_exec($curl));
        curl_close($curl);


        return $result;
    }

    public function sendCheckoutFormDetailRequest($baseUrl, $json, $authorizationData)
    {
        $url = $baseUrl . '/payment/iyzipos/checkoutform/auth/ecom/detail';
        return $this->sendCurlPostRequest($json, $authorizationData, $url);
    }

    public function postWebhookUrlKey($baseUrl, $json, $authorizationData)
    {
        $url = $baseUrl . '/payment/notification/update';
        return $this->sendCurlPostRequest($json, $authorizationData, $url);
    }

    public function sendOverlayScriptRequest($json, $authorizationData)
    {
        $baseUrl = "https://iyziup.iyzipay.com/";
        $url = $baseUrl . "v1/iyziup/protected/shop/detail/overlay-script";

        return $this->sendCurlPostRequest($json, $authorizationData, $url);
    }
}
