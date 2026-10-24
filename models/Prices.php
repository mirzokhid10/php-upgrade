<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "prices".
 *
 * @property int $id
 * @property int $client_id
 * @property int $contract_id
 * @property int $plan_id Plan
 * @property string $from_weight From weight
 * @property string $to_weight To weight
 * @property string $first_weight First weight
 * @property string $first_price First price
 * @property string $every_weight Every weight
 * @property string $weight_price Weight price
 * @property string $call_courier Call courier
 * @property string $call_price Call price
 * @property string $delivery_courier Delivery courier
 * @property string $delivery_price Delivery price
 * @property string $air Air
 * @property string $air_price Air price
 * @property string $far Far
 * @property string $far_price Far price
 * @property string $quantity Quantity
 * @property string $type Type
 * @property int $start_datetime
 * @property int $end_datetime
 * @property int $created_at
 * @property int $updated_at
 * @property int $price_low
 * @property int $price_high
 */
class Prices extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'prices';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['client_id', 'contract_id', 'plan_id', 'start_datetime', 'end_datetime', 'created_at', 'updated_at', 'price_low', 'price_high'], 'integer'],
            [['from_weight', 'to_weight', 'first_weight', 'first_price', 'every_weight', 'weight_price', 'call_courier', 'call_price', 'delivery_courier', 'delivery_price', 'air', 'air_price', 'far', 'far_price', 'quantity', 'type'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'client_id' => Yii::t('app', 'Client ID'),
            'contract_id' => Yii::t('app', 'Contract ID'),
            'plan_id' => Yii::t('app', 'Plan ID'),
            'from_weight' => Yii::t('app', 'From Weight'),
            'to_weight' => Yii::t('app', 'To Weight'),
            'first_weight' => Yii::t('app', 'First Weight'),
            'first_price' => Yii::t('app', 'First Price'),
            'every_weight' => Yii::t('app', 'Every Weight'),
            'weight_price' => Yii::t('app', 'Weight Price'),
            'call_courier' => Yii::t('app', 'Call Courier'),
            'call_price' => Yii::t('app', 'Call Price'),
            'delivery_courier' => Yii::t('app', 'Delivery Courier'),
            'delivery_price' => Yii::t('app', 'Delivery Price'),
            'air' => Yii::t('app', 'Air price'),
            'air_price' => Yii::t('app', 'Air price'),
            'far' => Yii::t('app', 'Far'),
            'far_price' => Yii::t('app', 'Far Price'),
            'quantity' => Yii::t('app', 'Quantity'),
            'type' => Yii::t('app', 'Type'),
            'start_datetime' => Yii::t('app', 'Start Datetime'),
            'end_datetime' => Yii::t('app', 'End Datetime'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'price_low' => Yii::t('app', 'Price Low'),
            'price_high' => Yii::t('app', 'Price High'),
        ];
    }

    /** 
    * @return \yii\db\ActiveQuery 
    */
    public function getClient() 
    {
        return $this->hasOne(Client::className(), ['id' => 'client_id']); 
    }

    /** 
    * @return \yii\db\ActiveQuery 
    */
    public function getContract() 
    {
        return $this->hasOne(Contract::className(), ['id' => 'contract_id']); 
    }

    /** 
    * @return \yii\db\ActiveQuery 
    */
    public function getPlan() 
    {
        return $this->hasOne(Plan::className(), ['id' => 'plan_id']); 
    }
}
