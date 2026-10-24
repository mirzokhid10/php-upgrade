<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "clients".
 *
 * @property int $id
 * @property string $name Name
 * @property int $region_id Region Id
 * @property int $city_id City Id
 * @property string $address Address
 * @property int $phone_number Phone Number
 * @property int $contact_number Contact Number
 * @property string $other_number Other Number
 * @property int $inn INN
 * @property int $okpo OKPO
 * @property int $oked OKED
 * @property int $okonx OKONX
 * @property int $soogu SOOGU
 * @property string $bank_name Bank Name
 * @property int $bank_account Bank Account
 * @property string $director_fullname Director Fullname
 * @property int $contract_id Contract
 * @property int $plan_id Plan
 * @property int $created_at
 * @property int $updated_at
 * @property int $is_online_store Online store
 * @property int $in_warehouse In warehouse
 *
 * @property City $city
 * @property Region $region
 */
class Client extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clients';
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
            [['name', 'region_id', 'city_id', 'phone_number', 'inn'], 'required'],
            [['region_id', 'city_id', 'inn', 'okpo', 'oked', 'okonx', 'soogu', 'contract_id', 'plan_id', 'is_online_store', 'in_warehouse'], 'integer'],
            [['address', 'bank_account', 'mfo'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['name',  'phone_number', 'contact_number', 'other_number', 'bank_name', 'director_fullname'], 'string', 'max' => 255],
            [['inn'], 'unique'],
            [['city_id'], 'exist', 'skipOnError' => true, 'targetClass' => City::className(), 'targetAttribute' => ['city_id' => 'id']],
            [['region_id'], 'exist', 'skipOnError' => true, 'targetClass' => Region::className(), 'targetAttribute' => ['region_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name' => Yii::t('app', 'Name'),
            'region_id' => Yii::t('app', 'Region ID'),
            'city_id' => Yii::t('app', 'City ID'),
            'address' => Yii::t('app', 'Address'),
            'phone_number' => Yii::t('app', 'Phone Numnber'),
            'contact_number' => Yii::t('app', 'Contact Number'),
            'other_number' => Yii::t('app', 'Other Number'),
            'inn' => Yii::t('app', 'Inn'),
            'okpo' => Yii::t('app', 'Okpo'),
            'oked' => Yii::t('app', 'Oked'),
            'okonx' => Yii::t('app', 'Okonx'),
            'soogu' => Yii::t('app', 'Soogu'),
            'bank_name' => Yii::t('app', 'Bank Name'),
            'bank_account' => Yii::t('app', 'Bank Account'),
            'director_fullname' => Yii::t('app', 'Director Fullname'),
            'contract_id' => Yii::t('app', 'Contract'),
            'plan_id' => Yii::t('app', 'Plan'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'mfo' => Yii::t('app', 'MFO'),
            'is_online_store' => Yii::t('app', 'Online store'),
            'in_warehouse' => Yii::t('app', 'Product in warehouse')
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCity()
    {
        return $this->hasOne(City::className(), ['id' => 'city_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegion()
    {
        return $this->hasOne(Region::className(), ['id' => 'region_id']);
    }

    public static function _all()
    {
        $lang = lang();
        $name = 'name';
        return ArrayHelper::map(self::find()->asArray()->all(), 'id', $name);
    }

    public static function searchSelect()
    {
        return ArrayHelper::map(self::find()->select(["id", "concat(name, ' - ', inn) as title"])->asArray()->all(), 'id', 'title');
    }

    public function getPrices()
    {
        return $this->hasMany(Prices::className(), ['client_id' => 'id']);
    }

    public function getContracts()
    {
        return $this->hasMany(Contracts::className(), ['client_id' => 'id']);
    }

    public function getActiveContract()
    {
        return $this->hasOne(Contracts::className(), ['id' => 'contract_id']);
    }

    public function getActivePlan()
    {
        return $this->hasOne(Plan::className(), ['id' => 'plan_id']);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Place your custom code here

            $this->phone_number = preg_replace("/\D/", '', $this->phone_number);
            $this->contact_number = preg_replace("/\D/", '', $this->contact_number);
            return true;
        } else {
            return false;
        }
    }

}
