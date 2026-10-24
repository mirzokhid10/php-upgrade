<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "sms".
 *
 * @property int $id
 * @property int $waybillid
 * @property string $phone
 * @property string $text
 * @property int $transactionid
 * @property int $smsid
 * @property int $status
 * @property string $statusdate
 * @property string $created_at
 * @property int $statusCode
 * @property int $errorCode
 * @property string $errMsg
 */
class Sms extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sms';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['waybillid', 'transactionid', 'smsid', 'status', 'statusCode', 'errorCode'], 'integer'],
            [['statusdate', 'created_at'], 'safe'],
            [['phone'], 'string', 'max' => 13],
            [['text', 'errMsg'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'waybillid' => Yii::t('app', 'Waybillid'),
            'phone' => Yii::t('app', 'Phone'),
            'text' => Yii::t('app', 'Text'),
            'transactionid' => Yii::t('app', 'Transactionid'),
            'smsid' => Yii::t('app', 'Smsid'),
            'status' => Yii::t('app', 'Status'),
            'statusdate' => Yii::t('app', 'Statusdate'),
            'created_at' => Yii::t('app', 'Created At'),
            'statusCode' => Yii::t('app', 'Status Code'),
            'errorCode' => Yii::t('app', 'Error Code'),
            'errMsg' => Yii::t('app', 'Err Msg'),
        ];
    }
}
