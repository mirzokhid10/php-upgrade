<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;


/**
 * SignupPhoneForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class SignupPhoneForm extends Model
{
    public $phone;

    public $error_message = '';

    private $_user = false;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['phone'], 'required', 'message' => 'Phone number is requied.'],
            // [['phone'], 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['phone' => 'username'], 'message' => 'Phone number not found.'],
        ];
    }


    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return $this->getUser();
        }

        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->phone);
        }

        return $this->_user;
    }
}
