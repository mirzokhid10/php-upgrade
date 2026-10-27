<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use app\models\Waybill;
use app\models\Branch;

/**
 * NotificationForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class NotificationForm extends Model
{
    public $order_count;
    public $barcode;
    public $error_message = '';


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // [['order_count'], 'required'],
            [['barcode'], 'string'],
            [['order_count'], 'integer', 'min' => 0],
        ];
    }

    public function saveNotification()
    {
        if ($this->validate()) {
            $count = $this->order_count ? ' Soni: '.$this->order_count : ''; 

            $from = '';
            if ($this->barcode) {
                $waybill = Waybill::findOne(['barcode' => $this->barcode]);
                if ($waybill) {
                    $from = '('.$waybill->from_fullname.' '.$waybill->fromCity->name_uz.')';
                }
            }

            $wid = null;
            $barcode = 1;
            if ($waybill) {
                $barcode = $waybill->barcode;
                $wid = $waybill->id;
            }
            // Yii::$app->smscomponent->send($wid, '998909984031', 'ZAYAVKA SROCHNO. Hamkor Bank plastik '.$from.': shtix kod:'.$barcode, true);
            if ($waybill) {
                if (in_array($waybill->from_city_id, [199,135,126,124])) {
                    // Yii::$app->smscomponent->send($wid, '998999993194', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, true);
                    Yii::$app->smscomponent->send('998994443911', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);

                }
                if ($waybill->from_city_id == 178) {
                    Yii::$app->smscomponent->send('998994443902', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                if ($waybill->from_city_id == 180) {
                    Yii::$app->smscomponent->send('998994443901', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                if ($waybill->from_city_id == 184) {
                    Yii::$app->smscomponent->send('998994443903', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                if ($waybill->from_city_id == 177) {
                    Yii::$app->smscomponent->send('998994443906', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                if ($waybill->from_city_id == 179) {
                    Yii::$app->smscomponent->send('998954443913', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                if ($waybill->from_region_id == 9) {
                    Yii::$app->smscomponent->send('998910228484', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                    Yii::$app->smscomponent->send('998918018686', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, $wid, true);
                }
                // Yii::$app->smscomponent->send($wid, '+998994448226', 'ZAYAVKA. Hamkor Bank plastik '.$from.': shtrix kod:'.$waybill->barcode, true);
                $branch = Branch::findOne($waybill->from_branch_id);
                if ($branch) {
                    $ph = str_replace(' ', '', $branch->phone);
                    $ph = str_replace('+', '', $ph);
                    if ($waybill->from_branch_id !== 15) {
                        if (!in_array($waybill->from_city_id, [199,135,126,124])) {
                            Yii::$app->smscomponent->send($wid, $ph, 'ZAYAVKA. Hamkor Bank plastik '.$from. ' shtrix kod:'.$waybill->barcode, true);
                        }
                    }
                    if ($waybill->from_branch_id == 14) {
                        Yii::$app->smscomponent->send($wid, '998994443990', 'ZAYAVKA. Hamkor Bank plastik '.$from. ' shtrix kod:'.$waybill->barcode, true);
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }
}
