<?php

namespace Iyzico\Iyzipay\Helper;

use stdClass;

class ResponseObjectHelper
{
    public function createTokenDetailObject($conversationId, $token)
    {
        $object = new stdClass();

        $object->locale = "tr";
        $object->conversationId = $conversationId;
        $object->token = $token;

        return $object;

    }

}
