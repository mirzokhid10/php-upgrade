<?php

namespace app\controllers;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\db\Query;

use app\components\Controller;
use app\models\User;
use app\models\StWaybill;
use app\models\AppVersion;

class StController extends Controller
{
	public function actionPackageCreated()
	{
		$label_id = null;
		$post = Yii::$app->request->post();
		$model = new StWaybill();
		$model->setAttributes($post);
		$model->user_id = $this->user()->id;

		$find_model = StWaybill::findOne(['tracking_number' => $model->tracking_number]);
		if ($find_model) {
			$find_model->setAttributes($post);
			if ($find_model->save(false)) {
				$label_id = $find_model->tracking_number;
			} else {
				throw new BadRequestHttpException('Tracking number exists');
			}
		} else {
			if ($model->save(false)) {
				$label_id = $model->tracking_number;
			} else {
				throw new BadRequestHttpException();
			}
		}

		return [
			'tracking_number' => $label_id
		];
	}

	public function user()
	{
		$user = Yii::$app->user->identity;

		if ($user->role_id == User::ROLE_AGENT) {
			return $user;
		}

		throw new ForbiddenHttpException('Your are not a agent');
	}
}