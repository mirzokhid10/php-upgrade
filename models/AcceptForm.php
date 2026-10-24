<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;


/**
 * AcceptForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class AcceptForm extends Model
{
    public $wids;
    public $barcodes;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['wids', 'barcodes'], 'string'],
        ];
    }

    public function accept()
    {
        $user = Yii::$app->user;
        if ($user) {
            if ($this->wids) {
                $ids = explode(',', $this->wids);
                if ($ids) {
                    foreach ($ids as $ix => $id) {
                        if ($id) {
                            $waybill = Waybill::findOne($id);
                            if ($waybill) {
                                if ($waybill->status == 9 && $waybill->is_delivery) {
                                    Waybill::updateAll(['delivery_courier_id' => $user->id], ['id' => $waybill->id]);
                                }
                                if (in_array($waybill->status, [1,2]) && $waybill->is_pickedup) {
                                    Waybill::updateAll(['pickup_courier_id' => $user->id], ['id' => $waybill->id]);
                                }
                            }
                        }
                    }
                }
            }
            if ($this->barcodes) {
                $barcodes = explode(',', $this->barcodes);
                if ($barcodes) {
                    foreach ($barcodes as $ix => $barcode) {
                        if ($barcode) {
                            $waybill = Waybill::findOne(['barcode' => $barcode]);
                            if ($waybill) {
                                if ($waybill->status !== 11 && $waybill->is_delivery) {
                                    Waybill::updateAll(['delivery_courier_id' => $user->id], ['id' => $waybill->id]);
                                }
                                if (in_array($waybill->status, [1,2]) && $waybill->is_pickedup) {
                                    Waybill::updateAll(['pickup_courier_id' => $user->id], ['id' => $waybill->id]);
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
}
