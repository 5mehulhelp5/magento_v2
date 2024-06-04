<?php

namespace Iyzico\Iyzipay\Helper;

use stdClass;

class ResponseObjectHelper
{
    /**
     * TODO: generateTokenDetailObject (yeni: createTokenDetailObject)
     */

    public function createTokenDetailObject($conversationId, $token)
    {
        $object = new stdClass();

        $object->locale = "tr";
        $object->conversationId = $conversationId;
        $object->token = $token;

        return $object;

    }

}
