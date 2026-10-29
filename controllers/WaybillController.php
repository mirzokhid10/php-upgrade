<?php

namespace app\controllers;

use Yii;
use yii\web\UnprocessableEntityHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\helpers\ArrayHelper;

use app\components\Controller;
use app\models\DeliveredForm;
use app\models\Waybill;
use app\models\History;
use app\models\User;
use app\models\Sign;
use app\models\AcceptForm;
use app\models\AcceptDeliveryForm;
use app\models\Calculate;
use app\models\CourierPickedForm;
use app\models\ClientExternalKeys;
use app\models\HandoverGet;

class WaybillController extends Controller
{

	public $status_info  = [
		13 => 1,
		14 => 2,
		2 => 3,
		1 => 4,
		3 => 5,
		4 => 6,
		5 => 7,
		6 => 8,
		9 => 9,
		10 => 10,
		11 => 11,
		12 => 12,
		7 => 13,
		15 => 15,
		16 => 16,
		53 => 53,
		54 => 54,
		55 => 55,
		56 => 56,
		57 => 57,
	];

	public function actionBotDetail()
	{
		$post = Yii::$app->request->post();

		$user = Yii::$app->user->identity;

		$waybill_id = null;

		// if (!isset($post['barcode']) && strlen($post['barcode']) < 10) {
		if (!isset($post['barcode']) || !is_scalar($post['barcode'])) {
			return [
				'message' => 'Barcode is too small',
				'code' => 404
			];
		}

		$condition = [
			'barcode' => $post['barcode']
		];

		if (isset($post['is_external_key']) && $post['is_external_key']) {
            if (empty($post['barcode']) || !is_scalar($post['barcode'])) {
                return ['message' => 'barcode is required', 'code' => 400];
            }
        
            $waybill_id = (new \yii\db\Query())
                ->select('waybill_id')
                ->from('client_external_keys')
                ->where(['external_id' => $post['barcode']])
                ->orderBy(['id' => SORT_DESC])
                ->scalar();
        
            if ($waybill_id) {
                $condition = ['id' => $waybill_id];
            } else {
                return ['message' => 'External key not found', 'code' => 404];
            }
        }
		

		$model = Waybill::find()->with(['fromBranch', 'toBranch', 'fromRegion', 'toRegion', 'fromCity', 'toCity', 'status'])->where($condition)->asArray()->one();
		if (!$model) {
			return [
				'message' => 'Barcode not found',
				'code' => 404
			];
		} else {
			$result = $model;

			$history = History::find()->with('data')->where(['waybill_id' => $model['id']])->asArray()->all();
			if ($history) {
				foreach ($history as $_history) {
					$data = [
						'message_ru' => $_history['message_ru'],
						'timestamp' => $_history['created_at'],
					];

					$result['history'][] = $data;
				}
			}
		}

		$result['code'] = 200;
		$result['message'] = "Founded";

		return $result;
	}

	public function actionDetail()
	{
		$post = Yii::$app->request->post();

		$user = Yii::$app->user->identity;

		$waybill_id = null;

		// if (!isset($post['barcode']) && strlen($post['barcode']) < 10) {
		if (!isset($post['barcode']) || !is_scalar($post['barcode'])) {
			return [
				'message' => 'Barcode is too small',
				'code' => 404
			];
		}

		$condition = [
			'barcode' => $post['barcode']
		];

        if (isset($post['is_external_key']) && $post['is_external_key']) {
            if (empty($post['barcode']) || !is_scalar($post['barcode'])) {
                return ['message' => 'barcode is required', 'code' => 400];
            }
        
            $waybill_id = (new \yii\db\Query())
                ->select('waybill_id')
                ->from('client_external_keys')
                ->where(['external_id' => $post['barcode']])
                ->orderBy(['id' => SORT_DESC])
                ->scalar();
        
            if ($waybill_id) {
                $condition = ['id' => $waybill_id];
            } else {
                return ['message' => 'External key not found', 'code' => 404];
            }
        }

		if ($user->client_id !== 653) {
			$condition['from_client_id'] = $user->client_id;
		}

		$model = Waybill::find()->with(['fromBranch', 'toBranch', 'fromRegion', 'toRegion', 'fromCity', 'toCity'])->where($condition)->asArray()->one();
		if (!$model) {
			return [
				'message' => 'Barcode not found',
				'code' => 404
			];
		} else {
			$result = $model;
			ClientExternalKeys::updateAll(['is_open' => 1], ['waybill_id' => $waybill_id]);

			if (isset($model['status']) &&  isset($this->status_info[$model['status']])) {
				$result['status'] = $this->status_info[$model['status']];
			}

			$history = History::find()->with('data')->where(['waybill_id' => $model['id']])->asArray()->all();
			if ($history) {
				foreach ($history as $_history) {
					$data = [
						'message_ru' => $_history['message_ru'],
						'timestamp' => $_history['created_at'],
					];

					$result['history'][] = $data;
				}
			}
		}

		$result['code'] = 200;
		$result['message'] = "Founded";

		return $result;
	}

	public function actionDelivery($type = null, $is_short = null)
	{
		$condition = ['delivery_courier_id' => $this->user()->id, 'status' => [3, 10]];
		if ($this->user()->id == 193) {
			$condition = ['delivery_courier_id' => $this->user()->id, 'id' => [1002854, 1133033, 1167740, 1277722, 1277729, 1277733, 1314719, 1314720, 1314722, 1314723, 1314725]];
		}
		if ($type && in_array($type, [1, 2])) {
			if ($type == 2) {
				$condition['is_cod'] = 1;
			}
		}
		if ($is_short) {
			return Waybill::find()->select(['id', 'concat(to_fullname, " - ", barcode) as barcode'])->where($condition)->asArray()->all();
		}
		return Waybill::find()->select(['*', 'weight as weight', '(id-id+1) as photo, concat("Адрес: ", to_address) as parcel_list'])->where($condition)->asArray()->all();
		// return Waybill::find()->select(['*', 'concat(to_phone_number, " ", to_address) as weight'])->where($condition)->asArray()->all();
	}

	public function actionCardDelivery($type = null, $is_short = null)
	{
		$condition = ['delivery_courier_id' => $this->user()->id, 'status' => [3, 10]];

		if ($type && in_array($type, [1, 2])) {
			if ($type == 2) {
				$condition['is_cod'] = 1;
			}
		}
		if ($is_short) {
			return Waybill::find()->select(['id', 'concat(id, " - ", to_fullname) as barcode'])->where($condition)->asArray()->all();
		}
		return Waybill::find()->select(['*', 'weight as weight', '(id-id+1) as photo, concat("Адрес: ", to_address) as parcel_list'])->where($condition)->asArray()->all();
		// return Waybill::find()->select(['*', 'concat(to_phone_number, " ", to_address) as weight'])->where($condition)->asArray()->all();
	}

	public function actionOrders($type = null, $is_short = null)
	{
		$condition = ['delivery_courier_id' => $this->user()->id, 'id' => [1002854, 1133033, 1167740, 1277722, 1277729, 1277733, 1314719, 1314720, 1314722, 1314723, 1314725]];
		if ($type && in_array($type, [1, 2])) {
			if ($type == 2) {
				$condition['is_cod'] = 1;
			}
		}
		if ($is_short) {
			return Waybill::find()->select(['id', 'concat(to_fullname, " - ", barcode) as barcode'])->where($condition)->asArray()->all();
		}
		return Waybill::find()->select(['*', 'weight as weight', '(id-id+1) as photo'])->where($condition)->asArray()->all();
	}

	public function actionOrderPickedup()
	{
		$post = Yii::$app->request->post();

		$waybill = [];

		if (isset($post['id'])) {
			$waybill = Waybill::findOne($post['id']);
		}

		return [
			'message' => 'Ok',
			'code' => 200,
			'waybill' => $waybill
		];
	}

	public function actionGetparcel()
	{
		$post = Yii::$app->request->post();

		$waybill = [];

		if (isset($post['barcode'])) {
			$waybill = Waybill::find()->where(['barcode' => $post['barcode']])->one();
		}

		return [
			'message' => 'Ok',
			'code' => 200,
			'waybill' => $waybill
		];
	}

		public function actionComment()
	{
		$this->user(); 

		$post = Yii::$app->request->post();

		$waybill = NULL;
		if (isset($post['id'])) {
			$waybill = Waybill::findOne($post['id']);
		}

		$result = [];

		if ($waybill) {
			$comments = [
				'go_delivery' => "Yetkazish uchun boshlandi",
				'phone_not_found' => "Telefon mavjud emas",
				'phone_switch_off' => "Telefon o'chirilgan",
				'phone_wrong' => "Telefon raqam noto'g'ri",
				'phone_no_answer' => 'Telefonga javob bermadi',
				'wrong_address' => "Manzil noto",
			];

			$comment = $post['comment'] ?? null;

			if (in_array($comment, array_keys($comments), true)) {
				$result = [
					'id' => $waybill->id,
					'barcode' => $waybill->barcode,
					'comment_key' => $comment,
					'comment' => $comments[$comment],
				];
				$identity = Yii::$app->user->identity;
				
				$history = new History();
				$history->waybill_id = $waybill->id;
				$history->user_id = $identity->id;
				$history->message_uz = "User:".$identity->id. "Comment_:".$comment;
				$history->message_en = "User:".$identity->id. "Comment_:".$comment;
				$history->message_ru = "User:".$identity->id. "Comment_:".$comment;

				$history->save(false);

				if ($comment == 'go_delivery') {
					Waybill::updateAll(['delivery_courier_id' => $identity->id, 'status' => 10], ['id' => $waybill->id]);
				}
			} else {
				$result = 'Comment not found';
			}
		} else {
			$result = 'Waybill not found';
		}

		return $result;
	}

	public function actionPickup()
	{
		$condition = ['pickup_courier_id' => $this->user()->id, 'status' => [1, 2, 3]];
		return Waybill::find()->where($condition)->asArray()->all();
	}

	public function actionNew()
	{
		$post = Yii::$app->request->post();
		$model = new Waybill();
		$model->setDefaultValues();
		$model->setAttributes($post);

		$model->sign_file = UploadedFile::getInstanceByName('sign_file');

		if ($model->validate()) {
			$model->status = 2;
			if ($model->save(false)) {
				$model->saveSign();
				$model->generateBarcode();

				$calculate = new Calculate();
				$calculate->from_city_id =  $model->from_city_id;
				$calculate->to_city_id = $model->to_city_id;
				$calculate->from_client_id = $model->from_client_id;
				$calculate->weight = $model->weight;
				$calculate->is_delivery = $model->is_delivery ? 1 : 0;
				$calculate->call_courier = $model->is_pickedup ? 1 : 0;
				$calculate->transport_type = $model->transport_type;

				$prices = $calculate->calc();

				$model->amount = ($prices['price']) ? $prices['price'] : 0;
				$model->discount = ($prices['discount']) ? $prices['discount'] : 0;
				$model->total_price = ($prices['total_price']) ? $prices['total_price'] - $prices['discount'] : 0;

				if ($model->is_pod) {
					$model->payin_amount = $model->total_price;
				}

				if ($model->save(false)) {

					$identity = Yii::$app->user->identity;

					$history = new History();
					$history->waybill_id = $model->id;
					$history->user_id = $identity->id;

					if ($identity->id == 36) {
						$history->message_uz = $model->from_fullname . " ROBOT orqali yangi pochta qo'shdi: " . $model->barcode;
						$history->message_en = $model->from_fullname . ' add new parcel by ROBOT: ' . $model->barcode;
						$history->message_ru = $model->from_fullname . ' добавил(а) новый почту через ROBOT: ' . $model->barcode;
					} else {
						$history->message_uz = $model->from_fullname . " Kuryer ilova orqali yangi pochta qo'shdi: " . $model->barcode;
						$history->message_en = $model->from_fullname . ' add new parcel by Courier app: ' . $model->barcode;
						$history->message_ru = $model->from_fullname . ' добавил(а) новый почту через Курьер приложения: ' . $model->barcode;
					}

					$history->save(false);

					return $model;
				}
			}
		}
		return $model->errors;
	}

	public function actionNewParcel()
	{
		$post = Yii::$app->request->post();
		$model = new Waybill();

		$model->setDefaultValues();
		$model->setAttributes($post);

		if (!$model->weight) {
			$model->weight = 0.5;
		}

		$model->setLegalDefaultValues();

		$model->sign_file = UploadedFile::getInstanceByName('sign_file');

		if ($model->validate()) {
			if ($model->checkIfModelExists()) {
				Yii::$app->response->statusCode = 409;
				throw new ConflictHttpException(Yii::t('app', 'Record already exists'));
			} else {
				if ($model->save()) {
					$model->saveSign();
					$model->generateBarcode();

					$calculate = new Calculate();
					$calculate->from_city_id =  $model->from_city_id;
					$calculate->to_city_id = $model->to_city_id;
					$calculate->from_client_id = $model->from_client_id;
					$calculate->weight = $model->weight;
					$calculate->is_delivery = $model->is_delivery ? 1 : 0;
					$calculate->call_courier = $model->is_pickedup ? 1 : 0;
					$calculate->transport_type = $model->transport_type;

					$prices = $calculate->calc();

					$model->amount = ($prices['price']) ? $prices['price'] : 0;
					$model->discount = ($prices['discount']) ? $prices['discount'] : 0;
					$model->total_price = ($prices['total_price']) ? $prices['total_price'] - $prices['discount'] : 0;

					if ($model->from_client_id == 684) {
						$model->is_contract = 1;
						$model->contract_amount = $model->total_price;
					}

					if ($model->is_pod) {
						$model->payin_amount = $model->total_price;
					}

					if ($model->save(false)) {

						$identity = Yii::$app->user->identity;

						$history = new History();
						$history->waybill_id = $model->id;
						$history->user_id = $identity->id;
						$history->message_uz = $identity->fullname . " API orqali yangi pochta qo'shdi: " . $model->barcode;
						$history->message_en = $identity->fullname . ' add new parcel by API: ' . $model->barcode;
						$history->message_ru = $identity->fullname . ' добавил(а) новый почту через API: ' . $model->barcode;
						$history->save(false);

						if (in_array($identity->id, [812])) {
                            Yii::$app->smscomponent->send('998909984031', 'ZAYAVKA. TERMINAL Hamkor Bank shtrix kod:' . $model->barcode, $model->id, true);
                            Yii::$app->smscomponent->send('998994443960', 'ZAYAVKA. TERMINAL Hamkor Bank shtrix kod:' . $model->barcode, $model->id, true);

						}

						return [
							'id' => $model->id,
							'barcode' => $model->barcode,
							'from_region_id' => $model->from_region_id,
							'from_city_id' => $model->from_city_id,
							'from_branch_id' => $model->from_branch_id,
							'from_fullname' => $model->from_fullname,
							'from_phone_number' => $model->from_phone_number,
							'weight' => $model->weight,
							'quantity' => $model->quantity,
							'parcel_type' => $model->parcel_type,
							'parcel_list' => $model->parcel_list,
							'to_region_id' => $model->to_region_id,
							'to_city_id' => $model->to_city_id,
							'to_fullname' => $model->to_fullname,
							'to_branch_id' => $model->to_branch_id,
							'to_phone_number' => $model->to_phone_number,
							'is_cod' => $model->is_cod,
							'cod_amount' => $model->cod_amount,
							'is_delivery' => $model->is_delivery,
							'to_address' => $model->to_address,
							'in_warehouse' => $model->in_warehouse,
							'amount' => $model->amount,
							'discount' => $model->discount,
							'total_price' => $model->total_price,
							'product_id' => $model->product_id,
						];
					}
				} else {
					Yii::$app->response->statusCode = 422;
				}
			}
		} else {
			Yii::$app->response->statusCode = 422;
		}
		return $model->errors;
	}

	public function actionCalculate()
	{
		$post = Yii::$app->request->post();
		$model = new Waybill();

		$model->setDefaultValues();
		$model->setAttributes($post);

		$model->setLegalDefaultValues();

		if ($model->validate()) {
			$calculate = new Calculate();
			$calculate->from_city_id =  $model->from_city_id;
			$calculate->to_city_id = $model->to_city_id;
			$calculate->from_client_id = $model->from_client_id;
			$calculate->weight = $model->weight;
			$calculate->is_delivery = $model->is_delivery ? 1 : 0;
			$calculate->call_courier = $model->is_pickedup ? 1 : 0;
			$calculate->transport_type = $model->transport_type;

			$prices = $calculate->calc();

			if ($prices) {
				$model->amount = ($prices['price']) ? $prices['price'] : 0;
				$model->discount = ($prices['discount']) ? $prices['discount'] : 0;
				$model->total_price = ($prices['total_price']) ? $prices['total_price'] - $prices['discount'] : 0;

				return [
					'amount' => $model->amount,
					'discount' => $model->discount,
					'total_price' => $model->total_price,
				];
			}
		}
		return $model->errors;
	}

	public function actionCurrentStatus()
	{
		$user = Yii::$app->user->identity;
		$post = Yii::$app->request->post();

		if (!isset($post['barcode'])) {
			throw new BadRequestHttpException('barcode is required');
		}

		if (!$user->client_id) {
			throw new BadRequestHttpException('This API for legal client');
		}

		$model = Waybill::findOne(['barcode' => $post['barcode'], 'from_client_id' => $user->client_id]);
		if (!$model) {
			throw new NotFoundHttpException('barcode does not exists');
		}

		$result = [
			'id' => $model->id,
			'barcode' => $model->barcode,
			'status' => $model->status,
			'delivery_datetime' => $model->delivery_datetime,
			'history' => []
		];

		if (in_array($user->client_id, [8, 581])) {
			$result['cod_amount'] = $model->cod_amount;
			$result['total_price'] = $model->total_price;
		}

		if (in_array($user->client_id, [4, 504]) and isset($this->status_info[$model->status])) {
			$result['status'] = $this->status_info[$model->status];
		}

		$history = History::find()->with('data')->where(['waybill_id' => $model->id])->asArray()->all();
		if ($history) {
			foreach ($history as $_history) {
				$data = [
					'message_ru' => $_history['message_ru'],
					'timestamp' => $_history['created_at'],
					'data' => []
				];

				if ($_history['data']) {
					foreach ($_history['data'] as $_data) {
						$data['data'][] = [
							'column_name' => $_data['column_name'],
							'old_val' => $_data['old_val'],
							'new_val' => $_data['new_val'],
						];
					}
				}

				$result['history'][] = $data;
			}
		}

		return $result;
	}

	public function actionSignPhoto()
	{
		$user = Yii::$app->user->identity;
		$post = Yii::$app->request->post();

		if (!isset($post['barcode'])) {
			throw new BadRequestHttpException('barcode is required');
		}

		if (!$user->client_id) {
			throw new BadRequestHttpException('This API for legal client');
		}

		$model = Waybill::findOne(['barcode' => $post['barcode'], 'from_client_id' => $user->client_id]);
		if (!$model) {
			throw new NotFoundHttpException('barcode does not exists');
		}

		$year = date('Y', $model->created_at);
		$month = date('m', $model->created_at);
		$day = date('d', $model->created_at);
		$sign_dir = "/var/www/aktivexpress/web/files/waybill/signs/" . $year . "/" . $month . "/" . $day . "/" . $model->id . "/photo.png";

		if (file_exists($sign_dir)) {
			Yii::$app->response->sendFile($sign_dir)->send();
		} else {
			$sign_dir = "/var/www/aktivexpress/web/files/waybill/signs/photos/" . $model->id . "/_" . $model->id . '.jpg';
			if (file_exists($sign_dir)) {
				Yii::$app->response->sendFile($sign_dir)->send();
			} else {
				Yii::$app->response->statusCode = 404;
				throw new NotFoundHttpException('Sign photo not found');
			}
		}
	}


	public function actionConfirmDelivery()
	{
		$user = Yii::$app->user->identity;
		$post = Yii::$app->request->post();

		if (!isset($post['barcode'])) {
			throw new BadRequestHttpException('barcode is required');
		}

		if (!$user->client_id) {
			throw new BadRequestHttpException('This API for legal client');
		}

		$model = Waybill::findOne(['barcode' => $post['barcode'], 'from_client_id' => $user->client_id]);
		if (!$model) {
			throw new NotFoundHttpException('barcode does not exists');
		}

		$result = [
			'id' => $model->id,
			'barcode' => $model->barcode,
			'status' => 11,
			'old_status' => null,
		];

		if (in_array($user->client_id, [4, 504]) and isset($this->status_info[$model->status])) {
			$result['old_status'] = $this->status_info[$model->status];
		}

		Waybill::updateAll(['status' => 11], ['id' => $model->id]);

		$history = new History();
		$history->waybill_id = $model->id;
		$history->user_id = $user->id;
		$history->message_uz = "sms. pincode. aktivlashdi " . $model->barcode;
		$history->message_en = "sms. pincode. activated " . $model->barcode;
		$history->message_ru = "sms. pincode. активирован " . $model->barcode;
		$history->save(false);

		HandoverGet::updateAll(['status' => 1], ['waybill_id' => $model->id]);

		return $result;
	}

	public function actionDelivered()
	{
		$this->user(); // couriers only: answers 403 to everybody else
		$error_msg = '';
		$post = Yii::$app->request->post();
		$model = new Sign();
		$model->setAttributes($post);

		$model->sign_file = UploadedFile::getInstanceByName('sign_file');
		$model->photo = UploadedFile::getInstanceByName('photo');
		$model->user_id = Yii::$app->user->id;
		$model->is_delivery = 1;

		if ($model->validate()) {
			$model->saveSign();
			if ($model->save(false)) {
				return [
					'status' => 'ok',
					'msg' => $model->msg,
					'user_id' => $model->user_id
				];
			}
		} else {
			if ($model->errors) {
				foreach ($model->errors as $field => $errors) {
					if ($errors) {
						foreach ($errors as $error) {
							$error_msg .= $field . ': ' . $error . ' ';
						}
					}
				}
			}
		}

		throw new BadRequestHttpException($error_msg);
	}

	public function actionHandoverStatus()
	{
		$post = Yii::$app->request->queryParams;

		if (!isset($post['wid'])) {
			throw new BadRequestHttpException('wid is required');
		}

		$user = Yii::$app->user->identity;
		if (!$user->client_id) {
			throw new ForbiddenHttpException('This API for legal client');
		}

		Waybill::updateAll(['status' => 57], ['id' => $post['wid'], 'from_client_id' => $user->client_id]);

		return ['status' => $post['wid']];
	}

	public function actionPicked()
	{
		$this->user(); // couriers only: answers 403 to everybody else
		$post = Yii::$app->request->post();
		$model = new Sign();
		$model->setAttributes($post);

		$model->sign_file = UploadedFile::getInstanceByName('sign_file');
		$model->user_id = Yii::$app->user->id;
		$model->is_delivery = 0;

		if ($model->validate()) {
			$model->saveSign();
			if ($model->save(false)) {
				return ['status' => 'ok'];
			}
		}
		return $model->errors;
		throw new BadRequestHttpException('failed');
	}


	public function actionCourierPicked()
	{
		$post = Yii::$app->request->post();
		$model = new CourierPickedForm();
		$model->setAttributes($post);

		if ($model->validate()) {
			if ($model->changeStatus()) {
				return ['status' => 'ok'];
			} else {
				return ['status' => 'error'];
			}
		}
		return $model->errors;
		throw new BadRequestHttpException('failed');
	}

	public function actionAccept()
	{
		$this->user(); // couriers only: answers 403 to everybody else
		$post = Yii::$app->request->post();
		$model = new AcceptForm();
		$model->setAttributes($post);

		if ($model->validate() && $model->accept()) {
			return ['status' => 'ok'];
		} else {
			return ['status' => 'error'];
		}
	}

	public function actionAcceptDelivery()
	{
		$this->user(); // couriers only: answers 403 to everybody else
		$post = Yii::$app->request->post();
		$model = new AcceptDeliveryForm();
		$model->setAttributes($post);

		if ($model->accept()) {
			return [
				'barcode' => $model->waybill->barcode,
				'to_fullname' => $model->waybill->to_fullname,
				'to_phone_number' => $model->waybill->to_phone_number,
				'pickup_courier_id' => $model->waybill->pickup_courier_id,
				'delivery_courier_id' => $model->waybill->delivery_courier_id,
				'old_status' => $model->waybill->old_status,
				'status' => $model->waybill->status,
			];
		}

		return $model->errorMsg;
	}

	public function actionForSign()
	{
		return [];
		$waybills = Waybill::find()->where(['for_sign' => 1, 'given_user_id' => Yii::$app->user->id])->asArray()->all();
		if (!$waybills) {
			$waybills = Waybill::find()->where(['cod_taken' => 1, 'cod_take_user' => Yii::$app->user->id])->asArray()->all();
		}
		return $waybills;
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
