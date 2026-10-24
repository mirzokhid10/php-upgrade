<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "st_waybill".
 *
 * @property int $id
 * @property int|null $user_id User
 * @property string|null $tracking_number Tracking number
 * @property string|null $first_name First name
 * @property string|null $last_name Last name
 * @property float|null $weight Weight
 * @property int|null $is_liquid Liquid
 * @property int|null $is_magnetic Magnetic
 * @property int|null $is_breakable Breakable
 * @property int|null $is_electronic Electronic
 * @property float|null $price Price
 * @property string|null $currency_code Currency code
 * @property float|null $total_price Total price
 * @property float|null $shipping_price Shipping price
 * @property string|null $address Address
 */
class StWaybill extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'st_waybill';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'is_liquid', 'is_magnetic', 'is_breakable', 'is_electronic'], 'integer'],
            [['weight', 'price', 'total_price', 'shipping_price'], 'number'],
            [['tracking_number', 'first_name', 'last_name', 'currency_code', 'address'], 'string', 'max' => 255],
            ['tracking_number', 'unique']
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
            'tracking_number' => Yii::t('app', 'Tracking number'),
            'first_name' => Yii::t('app', 'First name'),
            'last_name' => Yii::t('app', 'Last name'),
            'weight' => Yii::t('app', 'Weight'),
            'is_liquid' => Yii::t('app', 'Liquid'),
            'is_magnetic' => Yii::t('app', 'Magnetic'),
            'is_breakable' => Yii::t('app', 'Breakable'),
            'is_electronic' => Yii::t('app', 'Electronic'),
            'price' => Yii::t('app', 'Price'),
            'currency_code' => Yii::t('app', 'Currency code'),
            'total_price' => Yii::t('app', 'Total price'),
            'shipping_price' => Yii::t('app', 'Shipping price'),
            'address' => Yii::t('app', 'Address'),
        ];
    }
}
