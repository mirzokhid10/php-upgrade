<?php

namespace app\models;

use yii\base\Model;


/**
 * SignupCodeForm
 *
 * @property User|null $user This property is read-only.
 *
 */
class SignupCodeForm extends Model
{
    public $phone;
    public $code;

    private $_user = false;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['phone'], 'required', 'message' => 'Phone number is requied.'],
            [['code'], 'required', 'message' => 'SMS code is requied.'],
            [['phone'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['phone' => 'username'], 'message' => 'Phone number not found.'],
            [['code'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['code' => 'sms_code'], 'message' => 'Sms code incorrect'],
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
            $this->_user = User::findOne([
                'username' => $this->phone,
                'sms_code' => $this->code   
            ]);
        }

        return $this->_user;
    }
}
