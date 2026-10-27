<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "history_data".
 *
 * @property int $id
 * @property int $history_id
 * @property int $waybill_id
 * @property string $column_name
 * @property string $old_val
 * @property string $new_val
 * @property History $history
 * @property Waybill $waybill
 */
class HistoryData extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'history_data';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['history_id', 'waybill_id', 'created_at', 'updated_at'], 'integer'],
            [['column_name', 'old_val', 'new_val'], 'string', 'max' => 255],
            [['history_id'], 'exist', 'skipOnError' => true, 'targetClass' => History::class, 'targetAttribute' => ['history_id' => 'id']],
            [['waybill_id'], 'exist', 'skipOnError' => true, 'targetClass' => Waybill::class, 'targetAttribute' => ['waybill_id' => 'id']]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'history_id' => Yii::t('app', 'History ID'),
            'waybill_id' => Yii::t('app', 'Waybill ID'),
            'column_name' => Yii::t('app', 'Column Name'),
            'old_val' => Yii::t('app', 'Old Val'),
            'new_val' => Yii::t('app', 'New Val')
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHistory()
    {
        return $this->hasOne(History::class, ['id' => 'history_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWaybill()
    {
        return $this->hasOne(Waybill::class, ['id' => 'waybill_id']);
    }
}
