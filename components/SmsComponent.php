<?php

namespace app\components;

use Yii;
use app\models\Sms;
use yii\base\Component;

class SmsComponent extends Component
{
    public function send($phone, $text, $waybill_id = null, $is_call_center = null)
    {
        $expr = '/^[a-z][A-Z][0-9]*$/';

        if (preg_match('/[А-Яа-яЁё]/u', $text)) {
            $text = cyrillic_to_latin($text);
        }
        if (strlen($text) > 160) {
            $text = substr($text, 0, 160);
        }

        $sms = new Sms();
        $sms->phone = $phone;
        $sms->text = $text;
        $sms->waybillid = $waybill_id;
        $sms->send_status = 0; // Jo'natilmagan
        $sms->is_call_center = $is_call_center; // Call center jo'natganmi ?
        $sms->created_at = date('Y-m-d H:i:s');

        return ($sms->save(false)) ? true : false;
    }

}
