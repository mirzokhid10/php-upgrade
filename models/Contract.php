<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "contracts".
 *
 * @property int $id
 * @property int $region_id Region
 * @property int $city_id City
 * @property int $contract_order
 * @property int $client_id Client
 * @property string $number
 * @property int $has_contract
 * @property string $date Date
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Client $client
 * @property Region $region
 */
class Contract extends \yii\db\ActiveRecord
{
    public $set_client = 0;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'contracts';
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
            [['region_id', 'city_id', 'contract_order', 'client_id', 'has_contract', 'created_at', 'updated_at'], 'integer'],
            [['date'], 'safe'],
            [['number'], 'string', 'max' => 255],
            [['client_id'], 'exist', 'skipOnError' => true, 'targetClass' => Client::className(), 'targetAttribute' => ['client_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'region_id' => Yii::t('app', 'Region ID'),
            'city_id' => Yii::t('app', 'City ID'),
            'contract_order' => Yii::t('app', 'Contract Order'),
            'client_id' => Yii::t('app', 'Client ID'),
            'number' => Yii::t('app', 'Contract number'),
            'has_contract' => Yii::t('app', 'Has Contract'),
            'date' => Yii::t('app', 'Date'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
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
    public function getRegion()
    {
        return $this->hasOne(Region::className(), ['id' => 'region_id']);
    }
}
