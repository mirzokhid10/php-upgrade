<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;


/**
 * AcceptDeliveryForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class AcceptDeliveryForm extends Model
{
    public $barcode;

    public $waybill;
    
    public $errorMsg = '';

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['barcode'], 'required', 'message' => 'Barcode is requied.'],
            [['barcode'], 'exist', 'skipOnError' => true, 'targetClass' => Waybill::class, 'targetAttribute' => ['barcode' => 'barcode'], 'message' => 'Barcode not found.'],
        ];
    }

    public function accept()
    {
        $user = Yii::$app->user;
        $this->waybill = Waybill::findOne(['barcode' => $this->barcode]);
        $waybill = $this->waybill;
        if ($this->validate()) {
	        
	        if (in_array($waybill->status, [1])) {
	            Waybill::updateAll(['pickup_courier_id' => $user->id, 'status' => 3, 'old_status' => 1], ['id' => $waybill->id]);
	        }
	        if (in_array($waybill->status, [2])) {
	            Waybill::updateAll(['pickup_courier_id' => $user->id, 'status' => 3, 'old_status' => 2], ['id' => $waybill->id]);
	            if (in_array($waybill->from_client_id, [504,684])){
	            	$check_handoverget = HandoverGet::find()->where(['waybill_id' => $waybill->id])->all();
	            	if (!$check_handoverget) {
		            	Yii::$app->db->createCommand()->insert(HandoverGet::tableName(), [
		            		'waybill_id' => $waybill->id,
		            		'user_id' => $user->id,
		            		'status' => 0
		            	])->execute();
		            	Waybill::updateAll(['delivery_courier_id' => $user->id], ['id' => $waybill->id]);
	            	}
	            }
	        }
	        if (in_array($waybill->status, [3])) {
	            Waybill::updateAll(['delivery_courier_id' => $user->id, 'status' => 10, 'old_status' => 3], ['id' => $waybill->id]);
	        }
	        if (in_array($waybill->status, [4])) {
	            Waybill::updateAll(['delivery_courier_id' => $user->id, 'status' => 10, 'old_status' => 4], ['id' => $waybill->id]);
	        }

	        if (in_array($waybill->status, [6])) {
	            Waybill::updateAll(['delivery_courier_id' => $user->id, 'status' => 10, 'old_status' => 6], ['id' => $waybill->id]);
	        }

	        if (in_array($waybill->status, [15])) {
	            Waybill::updateAll(['delivery_courier_id' => $user->id, 'status' => 10, 'old_status' => 15], ['id' => $waybill->id]);
	        }

	        if (in_array($waybill->status, [10]) && $waybill->delivery_courier_id !== $user->id) {
	            Waybill::updateAll(['delivery_courier_id' => $user->id, 'status' => 10, 'old_status' => 10], ['id' => $waybill->id]);
	        }
	        $this->waybill = Waybill::findOne(['barcode' => $this->barcode]);

			$history = new History();
			$history->waybill_id = $waybill->id;
			$history->user_id = $user->id;
			$history->message_uz = $user->identity->fullname." pochtani qabul qilib oldi.";
			$history->message_en = $user->identity->fullname." accept waybill himself";
			$history->message_ru = $user->identity->fullname." принял(а) посылку";
			$history->save(false);

        	return true;
        } else {
			if ($this->errors) {
				foreach ($this->errors as $field => $messages) {
					if (count($messages) == 1) {
						$this->errorMsg = $messages[0];
					} else {
						$this->errorMsg[] = $messages;
					}
				}
			}
			return false;
        }
    }
}
