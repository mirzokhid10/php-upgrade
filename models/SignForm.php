<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;


/**
 * SignForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class SignForm extends Model
{
    public $wid;
    public $full_name;
    public $latitude;
    public $longitude;
    public $sign;

    public $is_delivery = true;

    public $error_message = '';


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['wid', 'full_name'], 'required'],
            [['latitude', 'longitude'], 'safe'],
            ['full_name', 'string', 'max' => 255],
            [['wid'], 'exist', 'skipOnError' => true, 'targetClass' => Waybill::class, 'targetAttribute' => ['wid' => 'id']],
            ['sign', 'image', 'maxSize' => 2097152, 'extensions' => ['png']],
        ];
    }

    public function saveSign()
    {
        $waybill = Waybill::findOne($this->wid);
        $sign_dir = Yii::$app->params['sign_dir'] . date('Y/m/d', $waybill->created_at) . '/' . $this->wid . '/';
        if (!is_dir($sign_dir)) {
            FileHelper::createDirectory($sign_dir);
        }

        $sign_file_name = 'pickup_sign.png';
        if ($this->is_delivery) {
            $sign_file_name = 'delivery_sign.png';
        }

        if ($this->sign->saveAs($sign_dir . $sign_file_name)) {
            return true;
        } else {
            $this->error_message = 'Failed save sign file.';
            return false;
        }
    }
}
