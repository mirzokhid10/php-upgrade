<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;


class CallingCourierRequest extends ActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_NEW = 1;
    const STATUS_ASSIGNED = 2;
    const STATUS_ACCEPTED = 3;
    const STATUS_ON_THE_WAY = 4;
    const STATUS_COMPLETED = 5;
    const STATUS_CANCELLED = 6;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%calling_courier_request}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }


    public function rules()
    {
        return [
            [['city_id', 'phone'], 'required'],
            [['city_id'], 'integer'],
            [['phone'], 'match', 'pattern' => '/^998\d{9}$/', 'message' => 'Phone number format is invalid.'],
            [['address'], 'string', 'max' => 255],
            [['latitude'], 'number', 'min' => -90, 'max' => 90],
            [['longitude'], 'number', 'min' => -180, 'max' => 180],
            [['latitude'], 'validateCoordinatesPair'],
            [['city_id'], 'exist', 'skipOnError' => true, 'targetClass' => City::class, 'targetAttribute' => ['city_id' => 'id']],
        ];
    }


    public function validateCoordinatesPair($attribute, $params)
    {
        $hasLat = $this->latitude !== null && $this->latitude !== '';
        $hasLng = $this->longitude !== null && $this->longitude !== '';

        if ($hasLat xor $hasLng) {
            $message = 'Latitude and longitude must be provided together.';
            $this->addError('latitude', $message);
            $this->addError('longitude', $message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'city_id' => Yii::t('app', 'City ID'),
            'region_id' => Yii::t('app', 'Region ID'),
            'phone' => Yii::t('app', 'Phone'),
            'address' => Yii::t('app', 'Address'),
            'latitude' => Yii::t('app', 'Latitude'),
            'longitude' => Yii::t('app', 'Longitude'),
            'status' => Yii::t('app', 'Status'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCity()
    {
        return $this->hasOne(City::class, ['id' => 'city_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegion()
    {
        return $this->hasOne(Region::class, ['id' => 'region_id']);
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function hasPendingRequest($userId)
    {
        return static::find()
            ->where(['user_id' => $userId, 'status' => self::STATUS_PENDING])
            ->exists();
    }
}
