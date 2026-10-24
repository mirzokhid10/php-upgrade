<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "city".
 *
 * @property int $id
 * @property int $region_id
 * @property string $name_uz
 * @property string $name_ru
 * @property string $name_en
 * @property int $c_order
 * @property int $ns11_code
 * @property int $soato
 *
 * @property Region $region
 */
class City extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'city';
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
            [['region_id', 'c_order', 'ns11_code', 'soato'], 'integer'],
            [['name_uz', 'name_ru', 'name_en'], 'string', 'max' => 100],
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
            'region_id' => Yii::t('app', 'Region ID'),
            'name_uz' => Yii::t('app', 'Name Uz'),
            'name_ru' => Yii::t('app', 'Name Ru'),
            'name_en' => Yii::t('app', 'Name En'),
            'c_order' => Yii::t('app', 'C Order'),
            'ns11_code' => Yii::t('app', 'Ns11 Code'),
            'soato' => Yii::t('app', 'Soato'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegion()
    {
        return $this->hasOne(Region::className(), ['id' => 'region_id']);
    }

    public static function _all($region_id = null, $group_by = false)
    {
        $condition = [];
        $lang = lang();
        $name = 'name_' . $lang;
        
        if ($region_id) {
            $condition['region_id'] = $region_id;
        }

        if ($group_by) {
            return ArrayHelper::map(self::find()->with('region')->orderBy('c_order')->all(), 'id', $name, 'region.'.$name);
        } else {
            return ArrayHelper::map(self::find()->where($condition)->orderBy('c_order')->all(), 'id', $name);
        }
    }
}
