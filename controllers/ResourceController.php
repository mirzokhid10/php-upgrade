<?php

namespace app\controllers;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\db\Query;

use app\components\Controller;
use app\models\Waybill;
use app\models\User;
use app\models\City;
use app\models\Status;
use app\models\AppVersion;

class ResourceController extends Controller
{
	public function actionRegions()
	{
		$query = new Query;
		$result = $query->select('*')->from('region')->all();

		return $result;
	}

	public function actionCities()
	{
		$query = new Query;
		$result = $query->select('*')->from('city')->all();

		return $result;
	}

	public function actionCitiesByRegion($id)
	{
		$query = new Query;
		$result = $query->select('*')->from('city')->where(['region_id' => $id])->all();

		return $result;
	}

	public function actionClients()
	{
		$query = new Query;
		$result = $query->select('*')->from('clients')->all();
		$result = $query->select(['id', 'name', 'address', 'phone_number'])
			->from('clients')
			->all();

		return $result;
	}

	public function actionVersion()
	{
		if ($app_verion = AppVersion::findOne(['status' => 1])) {
			return $app_verion->number;
		}

		return '1.0';
	}

	public function actionParcelTypes()
	{
		return [
			1 => 'box',
			2 => 'document',
			3 => 'package',
		];
	}

	public function actionParcelLists()
	{
		return [
			'smartphone',
			'smartwatch',
			'book',
		];
	}

	public function actionReturnTypes()
	{
		return [
			1 => 'money',
			2 => 'document',
			3 => 'waybill',
		];
	}


	public function actionStatus()
	{
		return [
			1 => 'archived',
			2 => 'deleted',
			3 => 'in_client',
			4 => 'new',
			5 => 'courier_1',
			6 => 'office_1',
			7 => 'warehouse',
			8 => 'dc',
			9 => 'office_2',
			10 => 'courier_2',
			11 => 'delivered',
			12 => 'canceled',
			13 => 'in_bag',
			14 => 'deleted',
			15 => 'courier_area',
			16 => 'employee',
			53 => 'cancel_from_office',
			54 => 'cancel_from_address',
			55 => 'cancel_wrong_address',
			56 => 'cancel_wrong_info',
			57 => 'handover',
		];
	}


	public function actionStatusLabels()
	{
		return Status::find()->select(['id', 'name', 'description_uz', 'description_ru', 'description_en'])->asArray()->all();
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