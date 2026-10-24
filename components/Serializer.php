<?php

namespace app\components;

class Serializer extends \yii\rest\Serializer 
{
    public function serialize($data) 
    {
    	$result = [];
        $parent_data = parent::serialize($data);
        $result['status'] = 'OK';
        $result['code'] = 200;
        $result['message'] = 'Success';
        $result['data'] = $data;

        return $result;
    }
}
