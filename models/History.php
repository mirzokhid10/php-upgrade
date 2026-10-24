<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "history".
 *
 * @property int $id
 * @property int $waybill_id
 * @property int $user_id
 * @property string $message_uz
 * @property string $message_en
 * @property string $message_ru
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Waybill $waybill
 * @property HistoryData[] $historyDatas
 */
class History extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'history';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className()
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['waybill_id', 'user_id', 'created_at', 'updated_at'], 'integer'],
            [['message_uz', 'message_en', 'message_ru'], 'string'],
            [['waybill_id'], 'exist', 'skipOnError' => true, 'targetClass' => Waybill::className(), 'targetAttribute' => ['waybill_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'waybill_id' => Yii::t('app', 'Waybill ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'message_uz' => Yii::t('app', 'Message Uz'),
            'message_en' => Yii::t('app', 'Message En'),
            'message_ru' => Yii::t('app', 'Message Ru'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWaybill()
    {
        return $this->hasOne(Waybill::className(), ['id' => 'waybill_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHistoryDatas()
    {
        return $this->hasMany(HistoryData::className(), ['history_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getData()
    {
        return $this->hasMany(HistoryData::className(), ['history_id' => 'id']);
    }

    public static function createNew($waybill)
    {
        $identity = identity();
        $history = new History();
        $history->waybill_id = $waybill->id;
        $history->user_id = $identity->id;
        $history->message_uz = $identity->fullname . ' (' . Region::findOne($identity->region_id)->short_name . ') ' . $waybill->barcode . ' ni qo‘shdi';
        $history->message_en = $identity->fullname . ' (' . Region::findOne($identity->region_id)->short_name . ') added ' . $waybill->barcode;
        $history->message_ru = $identity->fullname . ' (' . Region::findOne($identity->region_id)->short_name . ') добавил(а) ' . $waybill->barcode;
        $history->save();
    }
}
