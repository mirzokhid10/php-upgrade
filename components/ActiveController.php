<?php

namespace app\components;

use yii\filters\auth\HttpBearerAuth;

class ActiveController extends \yii\rest\ActiveController
{
	public function behaviors()
	{
		$behaviors = parent::behaviors();
		$behaviors['authenticator'] = [
			'class' => HttpBearerAuth::className(),
		];
		return $behaviors;
	}
}