<?php

namespace app\controllers;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use app\models\User;

use app\components\Controller;
use app\models\NotificationForm;

class NotificationController extends Controller
{
	public function actionReady()
	{
		$data = [];
		$post = Yii::$app->request->post();
		$model = new NotificationForm();
		$model->setAttributes($post);

		if ($model->saveNotification()) {
			$data = [
				'message' => 'Notification saved.',
			];
		} else {
			throw new BadRequestHttpException('Validation failed');
		}

		return $data;
	}

	public function user()
	{
		$user = Yii::$app->user->identity;

		if ($user->role_id == User::ROLE_COURIER) {
			return $user;
		}

		throw new ForbiddenHttpException('Your are not a courier');
	}
}
