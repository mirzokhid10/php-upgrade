<?php

namespace Tests\Support;

use yii\base\Component;

class FakeSmsComponent extends Component
{
    public static array $sent = [];

    public function send($waybill_id = null, $phone = null, $text = null, $is_call_center = null)
    {
         self::$sent[] = [
            'waybill_id'     => $waybill_id,
            'phone'          => $phone,
            'text'           => $text,
            'is_call_center' => $is_call_center,
        ];

        return true;
    }

    public static function reset()
    {
        self::$sent = [];
    }
}