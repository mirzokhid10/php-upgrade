<?php

namespace app\controllers;

use app\components\Controller;
use app\models\History;
use app\models\HistoryData;
use app\models\Waybill;
use Yii;


class TrackHistoryController extends Controller
{
    public function actionIndex()
    {
        try {
            $user = Yii::$app->user->identity;
            $waybill_id = Yii::$app->request->get('id');


            if (empty($waybill_id)) {
                return [
                    'success' => false,
                    'message' => 'Требуется ID посылки',
                ];
            }


            $query = Waybill::find()
                ->where(['id' => $waybill_id, 'is_test' => 0]);


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
                    'message' => 'Посылка не найдена',
                ];
            }


            $histories = History::find()
                ->where(['waybill_id' => $waybill->id])
                ->orderBy(['created_at' => SORT_ASC])
                ->asArray()
                ->all();

            $historyData = HistoryData::find()
                ->where(['waybill_id' => $waybill->id])
                ->orderBy(['id' => SORT_ASC])
                ->asArray()
                ->all();

            return [
                'success' => true,
                'message' => 'История успешно получена',
                'data' => [
                    'waybill_id' => $waybill->id,
                    'barcode' => $waybill->barcode,
                    'status' => $waybill->status,
                    'histories' => $histories,
                    'history_data' => $historyData,
                    'total_events' => count($histories)
                ]
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
}
