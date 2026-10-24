<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "client_external_keys".
 *
 * @property int $id
 * @property int|null $user_id User
 * @property string|null $external_id External id
 * @property int|null $waybill_id Waybill
 * @property int|null $is_open Is open
 */
class ClientExternalKeys extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'client_external_keys';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'waybill_id', 'is_open'], 'integer'],
            [['external_id'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User'),
            'external_id' => Yii::t('app', 'External id'),
            'waybill_id' => Yii::t('app', 'Waybill'),
            'is_open' => Yii::t('app', 'Is open'),
        ];
    }
}
