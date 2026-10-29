<?php

namespace app\models;

use Yii;
use yii\base\Model;


/**
 * CourierPickedForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class CourierPickedForm extends Model
{
    public $barcode;
    public $timestamp;
    public $courier_id;
    public $error_message = '';


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['barcode'], 'required'],
            [['timestamp'], 'integer', 'min' => 1000000000, 'max' => 9999999999],
            [['courier_id', 'timestamp'], 'safe'],
            [['courier_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['courier_id' => 'id']],
        ];
    }

    public function changeStatus()
    {
        $user = Yii::$app->user->identity;
        if (!$user->client_id) {
            $this->error_message = 'This API for legal client';
            return false;
        }
        $barcodes = [];

        // if (strpos($this->barcode ?? '', ',')) {
        if (strpos((string) $this->barcode, ',')) {
            $barcodes = explode(',', $this->barcode);
        } else {
            $barcodes = $this->barcode;
        }

        $timestamp = time();
        if ($this->timestamp) {
            $timestamp = $this->timestamp;
        }

        $waybills = Waybill::find()->where(['from_client_id' => $user->client_id, 'barcode' => $barcodes])->asArray()->all();

        if (count($waybills)) {
            if ($this->courier_id) {
                // Waybill::updateAll(['status' => 3, 'pickup_datetime' => $timestamp, 'pickup_courier_id' => $this->courier_id], ['barcode' => $barcodes]);
                Waybill::updateAll(['status' => 3, 'pickup_datetime' => $timestamp, 'pickup_courier_id' => $this->courier_id], ['barcode' => $barcodes, 'from_client_id' => $user->client_id]);
            } else {
                // Waybill::updateAll(['status' => 3, 'pickup_datetime' => $timestamp], ['barcode' => $barcodes]);
                Waybill::updateAll(['status' => 3, 'pickup_datetime' => $timestamp], ['barcode' => $barcodes, 'from_client_id' => $user->client_id]);
            }
            return true;
        } else {
            $this->error_message = 'Barcode not found.';
            return false;
        }
    }
}
