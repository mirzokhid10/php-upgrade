<?php

namespace app\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use app\models\LoginForm;
use app\models\SignupPhoneForm;
use app\models\SignupCodeForm;

class AuthController extends \yii\rest\Controller
{
	public function actionLoginCourier()
	{
		$post = Yii::$app->request->post();

		$model = new LoginForm();

		$model->setAttributes($post);

		$result = [];

		if ($model->login()) {
			$user = $model->user;

			$type = 1;


			if ($user->role_id == 2) {
				$type = 2;
			}

			$result['status'] = 'OK';
			$result['type'] = $type;
			$result['code'] = 200;
			$result['message'] = 'Success';
			$result['data'] = [
				'token' => $user->access_token
			];

			return $result;
		}

		$result['status'] = 'ERROR';
		$result['code'] = 200;
		$result['message'] = $model->error_message;
		$result['data'] = [];

		return $result;
	}

	public function actionSignupPhone()
	{
		$post = Yii::$app->request->post();

		$model = new SignupPhoneForm();

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
			$result['message'] = 'SMS code successfully sent';
			$result['data'] = NULL;

			$user->sms_code = random_int(10000, 99999);
			$user->save(false);

			Yii::$app->smscomponent->send($model->phone, 'KuryerGo mobil ilovasiga kirishni tasdiqlash kodi-'.$user->sms_code, null, 1);


			return $result;
		}

		$message = [];
		if ($model->errors) {
			foreach ($model->errors as $field => $messages) {
				if (count($messages) == 1) {
					$message = $messages[0];
				} else {
					$message = $messages;
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
