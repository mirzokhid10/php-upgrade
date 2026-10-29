<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 * This is the model class for table "waybill".
 *
 * @property int $id
 * @property string $barcode Barcode
 * @property int $parent_waybill_id Parent waybill
 * @property int $shipper_type Shipper type
 * @property int $from_branch_id Shipper branch
 * @property int $from_region_id Shipper region
 * @property int $from_city_id Shipper city
 * @property int $from_client_id Shipper legal client
 * @property int $from_individual_id Shipper individual
 * @property string $from_latitude Shipper latitude
 * @property string $from_longitude Shipper longitude
 * @property int $from_layolty_card Shipper individual layolty card
 * @property string $from_fullname Shipper fullname
 * @property string $from_phone_number Shipper phone number
 * @property string $from_address Shipper address
 * @property string $from_comment Shipper comment
 * @property int $to_branch_id Receiver branch
 * @property int $to_region_id Receiver region
 * @property int $to_city_id Receiver city
 * @property int $to_client_id Receiver legal client
 * @property int $to_individual_id Receiver individual
 * @property string $to_latitude Receiver latitude
 * @property string $to_longitude Receiver longitude
 * @property int $to_layolty_card Receiver individual layolty card
 * @property string $to_fullname Receiver fullname
 * @property string $to_phone_number Receiver phone number
 * @property string $to_address Receiver address
 * @property string $to_comment Receiver comment
 * @property int $transport_type Transport type
 * @property int $plan_id Plan
 * @property int $price_id Price
 * @property string $weight Weight
 * @property int $quantity Quantity
 * @property int $x X
 * @property int $y Y
 * @property int $z Z
 * @property string $amount Amount
 * @property string $discount Discount
 * @property string $total_price Total price
 * @property int $payment_type Payment type
 * @property int $is_pod is POD
 * @property int $is_cod is COD
 * @property int $is_delivery is Delivery
 * @property string $cod_amount COD amount
 * @property int $parcel_type Parcel type
 * @property string $parcel_list Parcel list
 * @property int $return_type Revert
 * @property int $formalize_user_id Formalize user
 * @property int $given_user_id Given user
 * @property int $pickup_courier_id Pickup courier
 * @property int $delivery_courier_id Delivery courier
 * @property int $last_user_id Last change user
 * @property int $pickup_datetime Pickup date and time
 * @property int $delivery_datetime Delivery date and time
 * @property int $sended_datetime Sended date and time
 * @property int $sended_date Sended date
 * @property int $received_datetime Received date and time
 * @property int $created_at Created at
 * @property int $updated_at Updated at
 * @property int $is_test Test
 * @property int $in_warehouse In warehouse
 */
class Waybill extends ActiveRecord
{
    protected $oldAttributes;

    const SHIPPER_TYPE_ONLINE_STORE = 3;
    const SHIPPER_TYPE_INDIVIDUAL = 1;
    const SHIPPER_TYPE_LEGAL = 2;

    public $search_formalized_user;
    public $search_given_user;
    public $search_pickup_courier;
    public $search_delivery_courier;

    public $pickup_time;
    public $received_time;
    public $sended_time;
    public $delivery_time;

    public $ser_unser_names = [];

    public $sign_file;

    public $fromFullName;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'waybill';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['from_city_id', 'to_city_id', 'from_fullname', 'from_phone_number', 'weight', 'quantity', 'to_phone_number', 'to_fullname'], 'required'],
            [['is_pickedup'], 'boolean'],
            //            [['sended_date'], 'date'],
            [['search_given_user', 'search_pickup_courier', 'search_delivery_courier'], 'string'],
            [['parent_waybill_id', 'shipper_type', 'from_branch_id', 'from_region_id', 'from_city_id', 'from_client_id', 'from_individual_id', 'from_layolty_card', 'to_branch_id', 'to_region_id', 'to_city_id', 'to_client_id', 'to_individual_id', 'to_layolty_card', 'transport_type', 'plan_id', 'price_id', 'quantity', 'payment_type', 'is_pod', 'is_cod', 'is_delivery', 'parcel_type', 'return_type', 'formalize_user_id', 'given_user_id', 'pickup_courier_id', 'delivery_courier_id', 'last_user_id', 'pickup_datetime', 'delivery_datetime', 'received_datetime', 'created_at', 'updated_at', 'formalize_user_id', 'is_test', 'in_warehouse', 'for_sign', 'cod_percent'], 'integer'],
            [['sended_datetime', 'parcel_list', 'pickup_time', 'searchable_data'], 'safe'],
            [['is_contract'], 'boolean'],
            [['weight', 'amount', 'discount', 'cod_amount', 'total_price', 'payin_amount', 'cod_percent_amount', 'contract_amount'], 'number'],
            [['barcode', 'from_latitude', 'from_longitude', 'from_fullname', 'fromFullName', 'from_phone_number', 'from_address', 'from_comment', 'to_latitude', 'to_longitude', 'to_fullname', 'to_phone_number', 'to_address', 'to_comment'], 'string', 'max' => 255],
            ['sign_file', 'image', 'maxSize' => 2097152, 'extensions' => ['png']],
            // [
            //     'payin_amount', 'required',
            //     'message' => 'PAYIN is required field',
            //     'when' => function ($model) {
            //         return ($model->is_pod == true and (empty($model->payin_amount) or $model->payin_amount == 0 or $model->payin_amount == ''));
            //     }
            // ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'barcode' => Yii::t('app', 'Barcode'),
            'parent_waybill_id' => Yii::t('app', 'Parent waybill'),
            'shipper_type' => Yii::t('app', 'Shipper type'),
            'from_branch_id' => Yii::t('app', 'Shipper branch'),
            'from_region_id' => Yii::t('app', 'Shipper region'),
            'from_city_id' => Yii::t('app', 'Shipper city'),
            'from_client_id' => Yii::t('app', 'Shipper legal client'),
            'from_individual_id' => Yii::t('app', 'Shipper individual'),
            'from_latitude' => Yii::t('app', 'Shipper latitude'),
            'from_longitude' => Yii::t('app', 'Shipper longitude'),
            'from_layolty_card' => Yii::t('app', 'Shipper individual layolty card'),
            'from_fullname' => Yii::t('app', 'Shipper fullname'),
            'from_phone_number' => Yii::t('app', 'Shipper phone number'),
            'from_address' => Yii::t('app', 'Shipper address'),
            'from_comment' => Yii::t('app', 'Shipper comment'),
            'to_branch_id' => Yii::t('app', 'Receiver branch'),
            'to_region_id' => Yii::t('app', 'Receiver region'),
            'to_city_id' => Yii::t('app', 'Receiver city'),
            'to_client_id' => Yii::t('app', 'Receiver legal client'),
            'to_individual_id' => Yii::t('app', 'Receiver individual'),
            'to_latitude' => Yii::t('app', 'Receiver latitude'),
            'to_longitude' => Yii::t('app', 'Receiver longitude'),
            'to_layolty_card' => Yii::t('app', 'Receiver individual layolty card'),
            'to_fullname' => Yii::t('app', 'Receiver fullname'),
            'to_phone_number' => Yii::t('app', 'Receiver phone number'),
            'to_address' => Yii::t('app', 'Receiver address'),
            'to_comment' => Yii::t('app', 'Receiver comment'),
            'transport_type' => Yii::t('app', 'Transport type'),
            'plan_id' => Yii::t('app', 'Plan'),
            'price_id' => Yii::t('app', 'Price'),
            'weight' => Yii::t('app', 'Weight'),
            'quantity' => Yii::t('app', 'Quantity'),
            'status' => Yii::t('app', 'Status'),
            'amount' => Yii::t('app', 'Amount'),
            'discount' => Yii::t('app', 'Discount'),
            'total_price' => Yii::t('app', 'Total price'),
            'payment_type' => Yii::t('app', 'Payment type'),
            'is_pod' => Yii::t('app', 'is POD ?'),
            'is_cod' => Yii::t('app', 'is COD ?'),
            'is_delivery' => Yii::t('app', 'is Delivery ?'),
            'cod_amount' => Yii::t('app', 'COD amount'),
            'parcel_type' => Yii::t('app', 'Parcel type'),
            'parcel_list' => Yii::t('app', 'Parcel list'),
            'return_type' => Yii::t('app', 'Revert'),
            'formalize_user_id' => Yii::t('app', 'Formalize user'),
            'given_user_id' => Yii::t('app', 'Given user'),
            'pickup_courier_id' => Yii::t('app', 'Pickup courier'),
            'is_pickedup' => Yii::t('app', 'Is Courier picked up'),
            'delivery_courier_id' => Yii::t('app', 'Delivery courier'),
            'search_delivery_courier' => Yii::t('app', 'Delivery courier'),
            'last_user_id' => Yii::t('app', 'Last change user'),
            'pickup_datetime' => Yii::t('app', 'Pickup date and time'),
            'pickup_time' => Yii::t('app', 'Pickup date and time'),
            'delivery_datetime' => Yii::t('app', 'Delivery date and time'),
            'delivery_time' => Yii::t('app', 'Delivery date and time'),
            'sended_datetime' => Yii::t('app', 'Sended date and time'),
            'sended_time' => Yii::t('app', 'Sended date and time'),
            'sended_date' => Yii::t('app', 'Sended date'),
            'received_datetime' => Yii::t('app', 'Received date and time'),
            'received_time' => Yii::t('app', 'Received date and time'),
            'created_at' => Yii::t('app', 'Created at'),
            'updated_at' => Yii::t('app', 'Updated at'),
            'gender' => Yii::t('app', 'Gender'),
            'is_test' => Yii::t('app', 'Test'),
            'in_warehouse' => Yii::t('app', 'In warehouse')
        ];
    }

    public function setDefaultValues()
    {
        $user = Yii::$app->user->identity;

        $this->shipper_type = self::SHIPPER_TYPE_INDIVIDUAL;
        $this->formalize_user_id = $user->id;
        $this->from_region_id = $user->region_id;
        $this->from_city_id = $user->city_id;
        $this->from_branch_id = $user->branch_id;

        $this->parcel_type = 3;
        $this->payment_type = 1;
        $this->quantity = 1;
        $this->sended_date = date('Y-m-d');
    }

    public function setLegalDefaultValues()
    {
        $user = Yii::$app->user->identity;

        $this->shipper_type = self::SHIPPER_TYPE_ONLINE_STORE;
        $this->from_client_id = isset($user->client_id) ? $user->client_id : null;
        $this->status = 2;
    }

    public function generateBarcode()
    {
        $user = Yii::$app->user->identity;
        $startBarcode = $this->created_at;

        $region = Region::findOne($this->from_region_id);
        if ($region) {
            $startBarcode = $region->waybill_code;
        }

        $region = Region::findOne($this->to_region_id);;
        if ($region) {
            $startBarcode = $startBarcode . $region->waybill_code;
        }

        if ($user && $user->id == 576) {
            $barcode = $startBarcode . date('m') . '4' . $this->id;
        } else {
            $barcode = $startBarcode . date('m') . date('y') . $this->id;
        }

        if (strlen($barcode ?? '') < 12) {
            $barcode .= str_repeat('0', 12 - strlen($barcode ?? ''));
        }
        $this->barcode = $barcode;

        if (in_array($this->from_client_id, [813])) {
            $this->barcode = '0813' . date('m') . date('y') . $this->id;
        }

        return $barcode;
    }

    public static function labels($column_name)
    {
        $name = 'name_ru';
        $cities = ArrayHelper::map(City::find()->all(), 'id', $name);
        $regions = ArrayHelper::map(Region::find()->all(), 'id', $name);

        $all_statuses = Status::find()->all();
        $desc = 'description_ru';

        $result = [
            'return_type' => [
                1 => Yii::t('app', 'Return money'),
                2 => Yii::t('app', 'Return document'),
                3 => Yii::t('app', 'Return waybill'),
            ],
            'status' => ArrayHelper::map($all_statuses, 'id', $desc),
            'parcel_type' => [
                1 => Yii::t('app', 'Parcel box'),
                2 => Yii::t('app', 'Parcel document'),
                3 => Yii::t('app', 'Parcel package'),
            ],
            'payment_type' => [
                1 => Yii::t('app', 'Cash'),
                2 => Yii::t('app', 'Terminal'),
                3 => Yii::t('app', 'Pay on delivery') . '(PAYIN/POD)',
            ],
            'is_cod' => [
                0 => Yii::t('app', 'No'),
                1 => Yii::t('app', 'Yes')
            ],
            'is_delivery' => [
                0 => Yii::t('app', 'No'),
                1 => Yii::t('app', 'Yes')
            ],
            'is_pickedup' => [
                0 => Yii::t('app', 'No'),
                1 => Yii::t('app', 'Yes')
            ],
            'shipper_type' => [
                1 => Yii::t('app', 'Individual'),
                2 => Yii::t('app', 'Legal'),
                3 => Yii::t('app', 'Online store'),
            ],
            'transport_type' => [
                0 => Yii::t('app', 'Not set'),
                1 => Yii::t('app', 'Transport type'),
            ],
            'from_region_id' => $regions,
            'from_city_id' => $cities,
            'to_region_id' => $regions,
            'to_city_id' => $cities,
        ];

        return $result[$column_name];
    }

    public function checkIfModelExists()
    {
        $exists = self::find()
            ->andWhere(['shipper_type' => $this->shipper_type])
            ->andWhere(['from_client_id' => $this->from_client_id])
            ->andWhere(['from_city_id' => $this->from_city_id])
            ->andWhere(['to_city_id' => $this->to_city_id])
            ->andWhere(['from_phone_number' => $this->from_phone_number])
            ->andWhere(['sended_date' => $this->sended_date])
            ->andWhere(['weight' => $this->weight])
            ->andWhere(['quantity' => $this->quantity])
            ->andWhere(['from_comment' => $this->from_comment])
            ->andWhere(['parcel_list' => $this->parcel_list])
            ->andWhere(['parcel_type' => $this->parcel_type])
            ->andWhere(['to_phone_number' => $this->to_phone_number])
            ->andWhere(['to_fullname' => $this->to_fullname])
            ->andWhere(['return_type' => $this->return_type])
            ->orderBy('created_at DESC')
            ->one();
        if ($exists) {
            if ($exists->status == 14) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function afterFind()
    {
        $this->sended_time = date('Y-m-d H:i:s', $this->sended_time);
        $this->sended_datetime = date('Y-m-d H:i:s', $this->sended_datetime);
        $this->delivery_time = ($this->delivery_datetime) ? date('Y-m-d H:i:s', $this->delivery_datetime) : null;
        $this->pickup_time = date('Y-m-d H:i:s', $this->pickup_datetime);
        $this->received_time = date('Y-m-d H:i:s', $this->received_datetime);

        $this->oldAttributes = $this->attributes;

        parent::afterFind();
    }

    public static function getTotal($provider, $fieldName)
    {
        $total = 0;

        foreach ($provider as $item) {
            $total += $item[$fieldName];
        }
        return $total;
    }

    public function beforeSave($insert)
    {
        $identity = Yii::$app->user->identity;
        if ($this->fromFullName) {
            $this->from_fullname = $this->fromFullName;
        }
        if (in_array($identity->client_id, [4, 504, 684, 813])) {
            if ($this->from_city_id) {
                if ($this->from_city_id == 1726292) {
                    $this->from_city_id = 1726290;
                }
                if ($from_city = City::find()->where(['soato' => $this->from_city_id])->one()) {
                    $this->from_region_id = $from_city->region_id;
                    $this->from_city_id = $from_city->id;

                    $from_branch = Branch::find()->where(['region_id' => $from_city->region_id])->one();
                    if ($from_branch) {
                        $this->from_branch_id = $from_branch->id;
                    }
                }
            }
            if ($this->to_city_id == 1726292) {
                $this->to_city_id = 1726290;
            }
            if ($to_city = City::find()->where(['soato' => $this->to_city_id])->one()) {
                $this->to_region_id = $to_city->region_id;
                $this->to_city_id = $to_city->id;
                $to_branch = Branch::find()->where(['region_id' => $to_city->region_id])->one();
                if ($to_branch) {
                    $this->to_branch_id = $to_branch->id;
                }
            }

            if (in_array($identity->client_id, [813])) {
                $this->from_branch_id = 2;
                $this->from_region_id = 2;
                $this->from_city_id = 53;
                $this->to_branch_id = 2;
                $this->to_region_id = 2;
                $this->to_city_id = 53;
            }
        } else {
            if ($this->from_city_id) {
                $from_city = City::findOne($this->from_city_id);

                if ($from_city) {
                    $this->from_region_id = $from_city->region_id;

                    $from_branch = Branch::find()->where(['region_id' => $from_city->region_id])->one();
                    if ($from_branch) {
                        $this->from_branch_id = $from_branch->id;
                    }
                }
            }
            if ($this->to_city_id) {
                $to_city = City::findOne($this->to_city_id);
                if ($to_city) {

                    $this->to_region_id = $to_city->region_id;

                    $to_branch = Branch::find()->where(['region_id' => $to_city->region_id])->one();
                    if ($to_branch) {
                        $this->to_branch_id = $to_branch->id;
                    }
                }
            }
        }

        if ($this->is_cod == 0) {
            $this->cod_amount = 0;
        }
        if ($this->is_delivery == 0) {
            $this->to_address = null;
        }
        if ($this->sended_date and $this->isNewRecord) {
            $this->sended_datetime = strtotime($this->sended_date . ' ' . date('H:i:s'));
        }

        $percent = 2;
        if ($this->is_return_money && $this->return_money_amount > 0) {
            $this->cod_percent = $percent;
            $this->cod_percent_amount = ($this->return_money_amount * $percent) / 100;
        } elseif ($this->is_cod && $this->cod_amount > 2) {
            $this->cod_percent = $percent;
            $this->cod_percent_amount = ($this->cod_amount * $percent) / 100;
        }

        // $from_phone = str_replace(' ', '', $this->from_phone_number);
        // $to_phone = str_replace(' ', '', $this->to_phone_number);
        $from_phone = str_replace(' ', '', (string) $this->from_phone_number);
        $to_phone = str_replace(' ', '', (string) $this->to_phone_number);

        $searchable_data = $this->barcode . ' ' . $this->from_fullname . ' ' . $from_phone . ' ' . $this->from_address . ' ' . $this->from_comment . ' ' . $this->to_fullname . ' ' . $to_phone . ' ' . $this->to_address . ' ' . $this->to_comment;
        if ($this->from_client_id) {
            $client = Client::findOne($this->from_client_id);
            if ($client) {
                $searchable_data .= ' ';
                $searchable_data .= $client->name;
            }
        }
        $this->searchable_data = $searchable_data;

        // if (strlen($this->to_phone_number ?? '') == 9) {
        if (strlen((string) $this->to_phone_number) == 9) {
            if (preg_match('/(\d{2})(\d{3})(\d{2})(\d{2})$/', $this->to_phone_number, $matches)) {
                $this->to_phone_number = '+998' . $matches[1] . ' ' . $matches[2] . ' ' . $matches[3] . ' ' . $matches[4];
            }
        }
        return parent::beforeSave($insert);
    }

    public function afterUpdate()
    {
        $identity = identity();

        $history = new History();
        $history->waybill_id = $this->id;
        $history->user_id = $identity->id;
        $history->message_uz = $identity->fullname . ' ' . $this->barcode . ' ga o‘zgarishlar kiritdi';
        $history->message_en = $identity->fullname . ' has changed ' . $this->barcode;
        $history->message_ru = $identity->fullname . ' изменил(а) ' . $this->barcode;
        $history->save(false);

        if ($this->is_pickedup != $this->oldAttributes['is_pickedup']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "is_pickedup";
            $history_data->old_val = $this->oldAttributes['is_pickedup'];
            $history_data->new_val = $this->is_pickedup;
            $history_data->save(false);
        }
        if ($this->from_region_id != $this->oldAttributes['from_region_id']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_region_id";
            $history_data->old_val = $this->oldAttributes['from_region_id'];
            $history_data->new_val = $this->from_region_id;
            $history_data->save(false);
        }
        if ($this->from_city_id != $this->oldAttributes['from_city_id']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_city_id";
            $history_data->old_val = $this->oldAttributes['from_city_id'];
            $history_data->new_val = $this->from_city_id;
            $history_data->save(false);
        }
        if ($this->shipper_type != $this->oldAttributes['shipper_type']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "shipper_type";
            $history_data->old_val = $this->oldAttributes['shipper_type'];
            $history_data->new_val = $this->shipper_type;
            $history_data->save(false);
        }
        if ($this->from_client_id != $this->oldAttributes['from_client_id']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_client_id";
            $history_data->old_val = $this->oldAttributes['from_client_id'];
            $history_data->new_val = $this->from_client_id;
            $history_data->save(false);
        }
        if ($this->from_fullname != $this->oldAttributes['from_fullname']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_fullname";
            $history_data->old_val = $this->oldAttributes['from_fullname'];
            $history_data->new_val = $this->from_fullname;
            $history_data->save(false);
        }
        if ($this->from_phone_number != $this->oldAttributes['from_phone_number']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_phone_number";
            $history_data->old_val = $this->oldAttributes['from_phone_number'];
            $history_data->new_val = $this->from_phone_number;
            $history_data->save(false);
        }
        if ($this->from_layolty_card != $this->oldAttributes['from_layolty_card']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "from_layolty_card";
            $history_data->old_val = $this->oldAttributes['from_layolty_card'];
            $history_data->new_val = $this->from_layolty_card;
            $history_data->save(false);
        }
        if ($this->sended_datetime != $this->oldAttributes['sended_datetime']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "sended_datetime";
            $history_data->old_val = $this->oldAttributes['sended_datetime'];
            $history_data->new_val = $this->sended_datetime;
            $history_data->save(false);
        }
        if ($this->weight != $this->oldAttributes['weight']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "weight";
            $history_data->old_val = $this->oldAttributes['weight'];
            $history_data->new_val = $this->weight;
            $history_data->save(false);
        }
        if ($this->quantity != $this->oldAttributes['quantity']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "quantity";
            $history_data->old_val = $this->oldAttributes['quantity'];
            $history_data->new_val = $this->quantity;
            $history_data->save(false);
        }
        if ($this->parcel_type != $this->oldAttributes['parcel_type']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "parcel_type";
            $history_data->old_val = $this->oldAttributes['parcel_type'];
            $history_data->new_val = $this->parcel_type;
            $history_data->save(false);
        }
        if ($this->parcel_list != $this->oldAttributes['parcel_list']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "parcel_list";
            $history_data->old_val = $this->oldAttributes['parcel_list'];
            $history_data->new_val = $this->parcel_list;
            $history_data->save(false);
        }
        if ($this->return_type != $this->oldAttributes['return_type']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "return_type";
            $history_data->old_val = $this->oldAttributes['return_type'];
            $history_data->new_val = $this->return_type;
            $history_data->save(false);
        }
        if ($this->payment_type != $this->oldAttributes['payment_type']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "payment_type";
            $history_data->old_val = $this->oldAttributes['payment_type'];
            $history_data->new_val = $this->payment_type;
            $history_data->save(false);
        }
        if ($this->to_region_id != $this->oldAttributes['to_region_id']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "to_region_id";
            $history_data->old_val = $this->oldAttributes['to_region_id'];
            $history_data->new_val = $this->to_region_id;
            $history_data->save(false);
        }
        if ($this->to_city_id != $this->oldAttributes['to_city_id']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "to_city_id";
            $history_data->old_val = $this->oldAttributes['to_city_id'];
            $history_data->new_val = $this->to_city_id;
            $history_data->save(false);
        }
        if ($this->to_fullname != $this->oldAttributes['to_fullname']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "to_fullname";
            $history_data->old_val = $this->oldAttributes['to_fullname'];
            $history_data->new_val = $this->to_fullname;
            $history_data->save(false);
        }
        if ($this->to_phone_number != $this->oldAttributes['to_phone_number']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "to_phone_number";
            $history_data->old_val = $this->oldAttributes['to_phone_number'];
            $history_data->new_val = $this->to_phone_number;
            $history_data->save(false);
        }
        if ($this->amount != $this->oldAttributes['amount']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "amount";
            $history_data->old_val = $this->oldAttributes['amount'];
            $history_data->new_val = $this->amount;
            $history_data->save(false);
        }
        if ($this->discount != $this->oldAttributes['discount']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "discount";
            $history_data->old_val = $this->oldAttributes['discount'];
            $history_data->new_val = $this->discount;
            $history_data->save(false);
        }
        if ($this->total_price != $this->oldAttributes['total_price']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "total_price";
            $history_data->old_val = $this->oldAttributes['total_price'];
            $history_data->new_val = $this->total_price;
            $history_data->save(false);
        }
        if ($this->is_cod != $this->oldAttributes['is_cod']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "is_cod";
            $history_data->old_val = $this->oldAttributes['is_cod'];
            $history_data->new_val = $this->is_cod;
            $history_data->save(false);
        }
        if ($this->cod_amount != $this->oldAttributes['cod_amount']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "cod_amount";
            $history_data->old_val = $this->oldAttributes['cod_amount'];
            $history_data->new_val = $this->cod_amount;
            $history_data->save(false);
        }
        if ($this->is_delivery != $this->oldAttributes['is_delivery']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "is_delivery";
            $history_data->old_val = $this->oldAttributes['is_delivery'];
            $history_data->new_val = $this->is_delivery;
            $history_data->save(false);
        }
        if ($this->transport_type != $this->oldAttributes['transport_type']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "transport_type";
            $history_data->old_val = $this->oldAttributes['transport_type'];
            $history_data->new_val = $this->transport_type;
            $history_data->save(false);
        }
        if ($this->sended_date != $this->oldAttributes['sended_date']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "sended_date";
            $history_data->old_val = $this->oldAttributes['sended_date'];
            $history_data->new_val = $this->sended_date;
            $history_data->save(false);
        }
        if ($this->to_address != $this->oldAttributes['to_address']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "to_address";
            $history_data->old_val = $this->oldAttributes['to_address'];
            $history_data->new_val = $this->to_address;
            $history_data->save(false);
        }
        if ($this->updated_at != $this->oldAttributes['updated_at']) {

            $history_data = new HistoryData();

            $history_data->history_id = $history->id;
            $history_data->waybill_id = $this->id;
            $history_data->column_name = "updated_at";
            $history_data->old_val = $this->oldAttributes['updated_at'];
            $history_data->new_val = $this->updated_at;
            $history_data->save(false);
        }
    }

    public function saveSign()
    {
        if ($this->sign_file) {
            $sign_dir = Yii::$app->params['sign_dir'] . date('Y/m/d', $this->created_at) . '/' . $this->id . '/';
            if (!is_dir($sign_dir)) {
                FileHelper::createDirectory($sign_dir);
            }

            $sign_file_name = 'pickup_sign.png';

            if ($this->sign_file->saveAs($sign_dir . $sign_file_name)) {
            }
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFromBranch()
    {
        return $this->hasOne(Branch::class, ['id' => 'from_branch_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getToBranch()
    {
        return $this->hasOne(Branch::class, ['id' => 'to_branch_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFromRegion()
    {
        return $this->hasOne(Region::class, ['id' => 'from_region_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBag()
    {
        return $this->hasOne(Region::class, ['id' => 'from_region_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFromCity()
    {
        return $this->hasOne(City::class, ['id' => 'from_city_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getToRegion()
    {
        return $this->hasOne(Region::class, ['id' => 'to_region_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBranch()
    {
        return $this->hasOne(Branch::class, ['id' => 'current_branch_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getToCity()
    {
        return $this->hasOne(City::class, ['id' => 'to_city_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFromClient()
    {
        return $this->hasOne(Client::class, ['id' => 'from_client_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getToClient()
    {
        return $this->hasOne(Client::class, ['id' => 'to_client_id']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeliveryCourier()
    {
        return $this->hasOne(User::class, ['id' => 'delivery_courier_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(Status::class, ['id' => 'status']);
    }
}
