<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "handover_get".
 *
 * @property int $id
 * @property int|null $waybill_id waybill
 * @property int|null $user_id user
 */
class HandoverGet extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'handover_get';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['waybill_id'], 'integer'],
            [['user_id'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'waybill_id' => Yii::t('app', 'Waybill'),
            'user_id' => Yii::t('app', 'User'),
        ];
    }
}

