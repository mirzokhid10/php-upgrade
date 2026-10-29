<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "individuals".
 *
 * @property int $id
 * @property string $full_name Full Name
 * @property int $phone_number Phone Number
 * @property string $phone_number_other Phone Number Other
 * @property string $loyalty_card Loyalty Card
 * @property string $created_at
 * @property string $updated_at
 */
class Individuals extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'individuals';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['full_name', 'phone_number', 'loyalty_card', 'gender'], 'required'],
            [['phone_number'], 'string'],
            [['gender', 'is_aktiv'], 'integer'],
            [['discount'], 'number'],
            [['created_at', 'updated_at'], 'safe'],
            [['full_name', 'phone_number_other', 'loyalty_card'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'full_name' => Yii::t('app', 'Full Name'),
            'phone_number' => Yii::t('app', 'Phone Numnber'),
            'phone_number_other' => Yii::t('app', 'Other Number'),
            'loyalty_card' => Yii::t('app', 'Loyalty Card'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'gender' => Yii::t('app', 'Gender'),
            'is_aktiv' => Yii::t('app', 'Is Aktiv'),
            'discount' => Yii::t('app', 'Discount')
        ];
    }

    public function beforeSave($insert)
    {
        // $this->phone_number = str_replace(' ', '', $this->phone_number);
        $this->phone_number = str_replace(' ', '', (string) $this->phone_number);
        // $this->phone_number_other = str_replace(' ', '', $this->phone_number_other);
        $this->phone_number_other = str_replace(' ', '', (string) $this->phone_number_other);
        return parent::beforeSave($insert);
    }
}
