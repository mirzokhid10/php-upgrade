<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "region".
 *
 * @property int $id
 * @property string $name_uz
 * @property string $name_ru
 * @property string $name_en
 * @property int $c_order
 * @property string $soato
 * @property int $ns10_code
 * @property string $waybill_code
 *
 * @property City[] $cities
 */
class Region extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'region';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    // ActiveRecord::EVENT_BEFORE_UPDATE => ['updated'],
                ],
                // если вместо метки времени UNIX используется datetime:
                'value' => new Expression('NOW()'),
            ]
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['c_order', 'ns10_code'], 'integer'],
            [['name_uz', 'name_ru', 'name_en', 'waybill_code', 'short_name'], 'string', 'max' => 100],
            [['soato'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name_uz' => Yii::t('app', 'Name Uz'),
            'name_ru' => Yii::t('app', 'Name Ru'),
            'name_en' => Yii::t('app', 'Name En'),
            'c_order' => Yii::t('app', 'C Order'),
            'soato' => Yii::t('app', 'Soato'),
            'ns10_code' => Yii::t('app', 'Ns10 Code'),
            'waybill_code' => Yii::t('app', 'Waybill code'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCities()
    {
        return $this->hasMany(City::className(), ['region_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBranches()
    {
        return $this->hasMany(Branch::className(), ['region_id' => 'id']);
    }

    public static function _all()
    {
        $lang = lang();
        $name = 'name_' . $lang;
        return ArrayHelper::map(self::find()->orderBy('c_order')->all(), 'id', $name);
    }
}
