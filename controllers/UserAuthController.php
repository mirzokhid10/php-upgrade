<?php

namespace app\controllers;

use Yii;
use app\models\SignupCodeForm;
use app\models\SignupPhoneForm;

class UserAuthController extends \yii\web\Controller
{
    public function actionSignupPhone()
    {
        $post = Yii::$app->request->post();
        $model = new SignupPhoneForm();
        $model->setAttributes($post);

        if (!$model->validate(['phone'])) {
            return ['status' => 'ERROR', 'message' => $model->getFirstError('phone')];
        }


        $user = \app\models\User::findByUsername($model->phone);


        if (!$user) {
            $user = new \app\models\User();
            $user->username = $model->phone;
            $user->email = $model->phone . '@aktivexpress.uz';
            $user->generateAccessToken();
            $user->generateAuthKey();
            $user->setPassword(Yii::$app->security->generateRandomString());
        }


        $user->sms_code = (string)random_int(10000, 99999);
        if ($user->save(false)) {
            Yii::$app->smscomponent->send($model->phone, 'Aktiv Express mobil ilovasiga kirishni tasdiqlash kodi-:' . $user->sms_code, null, 1);
            return [
                'status' => 'OK',
                'code' => 200,
                'message' => 'SMS code sent successfully',
                'data' => null
            ];
        }

        return ['status' => 'ERROR', 'message' => 'Failed to save user.'];
    }
    
    public function actionSignupCode()
    {
        $post = Yii::$app->request->post();

        $model = new SignupCodeForm();

        $model->setAttributes($post);

        $result = [];

        if ($model->login()) {
            $user = $model->user;

            $type = 1;

            if ($user->role_id == 2) {
                $type = 2;
            }

            $result['status'] = 'OK';
            $result['code'] = 200;
            $result['message'] = 'Success';
            $result['data'] = [
                'token' => $user->access_token
            ];

            $user->sms_code = NULL;
            $user->save(false);

            return $result;
        }

        $message = [];
        if ($model->errors) {
            foreach ($model->errors as $field => $messages) {
                if (count($messages) == 1) {
                    $message = $messages[0];
                } else {
                    $message[] = $messages;
                }
            }
        }

        $result['status'] = 'ERROR';
        $result['code'] = 200;
        $result['message'] = $message;
        // $result['message'] = $model->errors;
        $result['data'] = NULL;

        return $result;
    }
}
