<?php

namespace app\controllers;

use app\components\Controller;
use Yii;
use app\models\Waybill;

class TrackSearchController extends Controller
{
    public function actionIndex()
    {
        try {

            $user = Yii::$app->user->identity;

            $text = Yii::$app->request->get('text') ?: Yii::$app->request->post('text');

            if (empty($text)) {
                return [
                    'success' => false,
                    'message' => 'Поисковый запрос обязателен'
                ];
            }

            $text = trim($text);

            $query = Waybill::find()
                ->with([
                    'fromCity',
                    'toCity',
                    'fromRegion',
                    'toRegion',
                    'status',
                    'fromClient',
                ]);

            $visibilityConditions = ['or'];

            if ($user->client_id) {
                $visibilityConditions[] = ['from_client_id' => $user->client_id];
            } else {
                $visibilityConditions[] = ['like', 'searchable_data', $user->username];
            }

            $query->andWhere($visibilityConditions);

            $query->andWhere(['like', 'searchable_data', $text]);


            $waybills = $query->limit(100)->all();

            if (empty($waybills)) {
                return [
                    'success' => false,
                    'message' => 'По запросу ничего не найдено',
                    'data' => []
                ];
            }


            $result = [];
            foreach ($waybills as $waybill) {
                $result[] = $this->formatWaybillCard($waybill);
            }

            return [
                'success' => true,
                'message' => 'Успешно найдено',
                'data' => $result,
                'count' => count($result)
            ];
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => YII_DEBUG ? $e->getMessage() : null
            ];
        }
    }


    public function actionDetail()
    {
        try {

            $user = Yii::$app->user->identity;

            $id = Yii::$app->request->get('id');

            if (empty($id)) {
                return [
                    'success' => false,
                    'message' => 'Штрих-код обязателен',
                ];
            }

            $query = Waybill::find()
                ->with([
                    'fromCity',
                    'toCity',
                    'fromRegion',
                    'toRegion',
                    'fromBranch',
                    'toBranch',
                    'status',
                    'fromClient',
                    'deliveryCourier'
                ])
                ->where(['id' => $id]);


            $visibilityConditions = ['or'];

            if (empty($user->client_id)) {
                $userPhone = $user->username;

                $visibilityConditions[] = ['from_phone_number' => $userPhone];
                $visibilityConditions[] = ['to_phone_number' => $userPhone];
            } else {
                $visibilityConditions[] = ['from_client_id' => $user->client_id];
            }

            $query->andWhere($visibilityConditions);

            $waybill = $query->one();

            if (!$waybill) {
                return [
                    'success' => false,
                    'message' => 'Информация не найдена',
                ];
            }

            return [
                'success' => true,
                'message' => 'Успешно',
                'data' => $this->formatWaybillDetail($waybill)
            ];
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => YII_DEBUG ? $e->getMessage() : null
            ];
        }
    }


    private function formatWaybillCard($waybill)
    {
        return $waybill;
    }


    private function formatWaybillDetail($waybill)
    {
        return $waybill;
    }
}
