<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "handover_pre".
 *
 * @property int $id
 * @property string|null $barcode Barcode
 * @property int|null $waybill_id Waybill
 * @property int|null $user_id User
 * @property int|null $status Status
 */
class HandoverPre extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'handover_pre';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['waybill_id', 'user_id', 'status'], 'integer'],
            [['barcode'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'barcode' => Yii::t('app', 'Barcode'),
            'waybill_id' => Yii::t('app', 'Waybill'),
            'user_id' => Yii::t('app', 'User'),
            'status' => Yii::t('app', 'Status'),
        ];
    }
}
