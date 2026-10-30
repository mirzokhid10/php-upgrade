<?php

namespace app\controllers;

use app\components\Controller;
use app\models\City;
use app\models\CallingCourierRequest;
use Yii;
use yii\filters\VerbFilter;

class CallingCourierController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'cities' => ['get'],
                    'create' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * Returns a list of cities for which courier requests are accepted.
     */
    public function actionCities()
    {
        try {
            $cities = City::find()
                ->select(['id', 'name_uz', 'name_ru', 'name_en'])
                ->orderBy(['c_order' => SORT_ASC])
                ->asArray()
                ->all();

            return [
                'success' => true,
                'data' => $cities,
            ];
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => YII_DEBUG ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Creates a courier request for the authenticated user.
     */
    public function actionCreate()
    {
        try {
            $user = Yii::$app->user->identity;

            # Check if the user already has an active courier request
            $activeRequest = CallingCourierRequest::find()
                ->where(['user_id' => $user->id])
                ->andWhere([
                    'status' => [
                        CallingCourierRequest::STATUS_NEW,
                        CallingCourierRequest::STATUS_ASSIGNED,
                        CallingCourierRequest::STATUS_PENDING,
                        CallingCourierRequest::STATUS_ACCEPTED,
                        CallingCourierRequest::STATUS_ON_THE_WAY,
                    ]
                ])
                ->exists();
            if ($activeRequest) {
                return [
                    'success' => false,
                    'message' => 'У вас уже есть активный запрос на вызов курьера.',
                ];
            }

            $model = new CallingCourierRequest();
            $model->setAttributes(Yii::$app->request->post());

            if (!$model->validate()) {
                $errors = $model->getErrors();
                $firstErrors = reset($errors);
            
                return [
                    'success' => false,
                    'message' => $firstErrors ? $firstErrors[0] : null,
                    'errors' => $errors,
                ];
            }

            $city = City::findOne($model->city_id);

            $model->user_id = $user->id;
            $model->region_id = $city->region_id;

            if (!$model->save(false)) {
                return [
                    'success' => false,
                    'message' => 'Не удалось создать запрос на вызов курьера',
                    'error' => YII_DEBUG ? $model->errors : null,
                ];
            }

            $model->refresh();

            $region = $model->region;

            return [
                'success' => true,
                'message' => 'Запрос на вызов курьера успешно создан',
                'data' => [
                    'id' => $model->id,
                    'phone' => $model->phone,
                    'address' => $model->address,
                    'latitude' => $model->latitude,
                    'longitude' => $model->longitude,
                    'status' => $model->status,
                    'created_at' => $model->created_at,
                    'city' => [
                        'id' => $city->id,
                        'name_uz' => $city->name_uz,
                        'name_ru' => $city->name_ru,
                        'name_en' => $city->name_en,
                    ],
                    'region' => $region ? [
                        'id' => $region->id,
                        'name_uz' => $region->name_uz,
                        'name_ru' => $region->name_ru,
                        'name_en' => $region->name_en,
                    ] : null,
                ],
            ];
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => YII_DEBUG ? $e->getMessage() : null,
            ];
        }
    }
}
