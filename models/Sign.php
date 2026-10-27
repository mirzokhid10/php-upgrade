<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\FileHelper;

/**
 * This is the model class for table "sign".
 *
 * @property int $id
 * @property int $user_id User
 * @property int $waybill_id Waybill
 * @property string $wids Waybill list
 * @property string $full_name Full name
 * @property string $latitude Latitude
 * @property string $longitude Longitude
 * @property int $is_delivery Longitude
 * @property int $datetime Date time
 * @property int $created_at Created at
 * @property int $updated_at Updated at
 */
class Sign extends \yii\db\ActiveRecord
{
    public $sign_file;
    public $photo;
    public $msg;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sign';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'waybill_id', 'is_delivery', 'created_at', 'updated_at', 'datetime'], 'integer'],
            [['full_name', 'latitude', 'longitude', 'barcode'], 'string', 'max' => 255],
            [['wids', 'barcodes', 'msg'], 'string'],
            [['waybill_id'], 'exist', 'skipOnError' => true, 'targetClass' => Waybill::class, 'targetAttribute' => ['waybill_id' => 'id']],
            ['sign_file', 'image', 'extensions' => ['png']],
            ['photo', 'image'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User'),
            'waybill_id' => Yii::t('app', 'Waybill ID'),
            'full_name' => Yii::t('app', 'Full Name'),
            'latitude' => Yii::t('app', 'Latitude'),
            'longitude' => Yii::t('app', 'Longitude'),
            'is_delivery' => Yii::t('app', 'Is Delivery'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    public function saveSign()
    {
        $identity = Yii::$app->user->identity;
        if ($this->sign_file) {
            if ($this->wids) {
                $ids = explode(',', $this->wids);
                if ($ids) {
                    foreach ($ids as $ix => $id) {
                        if ($id) {
                            $waybill = Waybill::findOne($id);
                            if ($waybill) {
                                $sign_dir = Yii::$app->params['sign_dir'] . date('Y/m/d', $waybill->created_at) . '/' . $waybill->id . '/';
                                if (!is_dir($sign_dir)) {
                                    FileHelper::createDirectory($sign_dir);
                                }

                                $sign_file_name = 'pickup_sign.png';
                                if ($this->is_delivery) {
                                    $sign_file_name = 'delivery_sign.png';
                                }

                                if ($waybill->status == 1) {
                                    $sign_file_name = 'pickup_sign.png';
                                }

                                if ($waybill->status >= 9) {
                                    $sign_file_name = 'delivery_sign.png';
                                }

                                if ($waybill->cod_taken) {
                                    $sign_file_name = 'given_money_sign.png';
                                    $waybill->cod_taken = 0;
                                }

                                if ($this->sign_file->saveAs($sign_dir . $sign_file_name, false)) {
                                    $history = new History();
                                    $history->waybill_id = $waybill->id;
                                    $history->user_id = $identity->id;
                                    $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ga elektron imzo saqlandi';
                                    $history->message_en = $identity->fullname . ' saved electron sign for ' . $waybill->barcode;
                                    $history->message_ru = $identity->fullname . ' сохранил(а) электрон подпись для ' . $waybill->barcode;
                                    $history->save();
                                }

                                if ($this->photo) {
                                    if ($this->photo->saveAs($sign_dir . 'photo.png', false)) {
                                        $history = new History();
                                        $history->waybill_id = $waybill->id;
                                        $history->user_id = $identity->id;
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ga rasm-hisobot saqlandi';
                                        $history->message_en = $identity->fullname . ' saved photo report for ' . $waybill->barcode;
                                        $history->message_ru = $identity->fullname . ' сохранил(а) электрон фотоотчет для ' . $waybill->barcode;
                                        $history->save();


                                        // if (in_array($waybill->from_client_id, [504,684])) {
                                        //     $handoverPre = HandoverPre::findOne(['waybill_id' => $waybill->id]);
                                        //     if ($handoverPre) {
                                        //         if ($handoverPre->status == 1) {
                                        //             $handoverPre->status = 2;
                                        //             $handoverPre->save(false);
                                        //         }
                                        //     } else {
                                        //         $_r = Yii::$app->db->createCommand()->insert(HandoverPre::tableName(), ['waybill_id' => $waybill->id, 'barcode' => $waybill->barcode, 'status' => 2])->execute();
                                        //     }
                                        // }
                                    }
                                }


                                $oldStatus = $waybill->status;
                                if ($this->is_delivery) {
                                    if (in_array($waybill->from_client_id, [504,684,3])) {
                                        if ($waybill->status == 10) {
                                            $waybill->status = 57;
                                            $this->msg .= "status=57;";
                                        } else {
                                            $waybill->status = 10;
                                            $this->msg .= "status=10;";
                                            // $oldStatus = 3;
                                            $waybill->delivery_courier_id = $identity->id;
                                        }
                                    } else {
                                        $waybill->status = 11;
                                    }
                                    $waybill->old_status = $oldStatus;
                                }
                                if (in_array($oldStatus, [1,2]) && !in_array($waybill->from_client_id, [504,684,3])) {
                                    $waybill->status = 3;
                                    $waybill->old_status = $oldStatus;
                                    $this->msg .= "status=3;";
				   $waybill->delivery_courier_id = $identity->id;
                                }
                                if ($this->datetime) {
                                    if ($this->is_delivery) {
                                        $waybill->delivery_datetime = $this->datetime;
                                    } else {
                                        $waybill->pickup_datetime = $this->datetime;
                                    }
                                }
                                $waybill->for_sign = 0;
                                if ($waybill->save(false)) {
                                    
                                    $history = new History();
                                    $history->waybill_id = $waybill->id;
                                    $history->user_id = $identity->id;
                                    if (in_array($oldStatus, [1,2])) {
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ni ' . $this->full_name .' dan qabul qilib oldi';
                                        $history->message_en = $identity->fullname . ' picked up ' . $waybill->barcode . ' from sender ' . $this->full_name;
                                        $history->message_ru = $identity->fullname . ' забрал(а) ' . $waybill->barcode . ' у отправителя ' . $this->full_name;
                                    } else {
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ni ' . $this->full_name .' ga yetkazdi';
                                        $history->message_en = $identity->fullname . ' delivered ' . $waybill->barcode . ' to ' . $this->full_name;
                                        $history->message_ru = $identity->fullname . ' доставил(а) ' . $waybill->barcode . ' в ' . $this->full_name;                                        
                                    }
                                    $history->save();

                                    if ($oldStatus !== 11 && !in_array($oldStatus, [1,2])) {
                                        $region = Region::findOne($waybill->to_region_id);
                                        $to_name = $waybill->to_fullname;
                                        if ($region) {
                                            $to_name .= "(".$region->short_name.")";
                                        }
                                        $from_phone_number = $waybill->from_phone_number;
                                        $from_phone_number = str_replace(' ', '', $from_phone_number);
                                        $message = "AKTIV: Uvajayemiy klient, Vasha pochta kod " . $waybill->barcode . " dostavlen/Hurmatli mijoz, pochtangiz ". $to_name ." ga yetkazildi.";
                                        if (strlen($message) >= 126) {
                                            $message = "AKTIV: Uvajayemiy klient, Vasha pochta kod " . $waybill->barcode . " dostavlen/Pochtangiz ". $to_name ." ga yetkazildi.";
                                        }
                                        Yii::$app->smscomponent->send($from_phone_number, $message, $waybill->id);

                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ($this->barcodes) {
                $barcodes = explode(',', $this->barcodes);
                if ($barcodes) {
                    foreach ($barcodes as $ix => $barcode) {
                        if ($barcode) {
                            $waybill = Waybill::findOne(['barcode' => $barcode]);
                            if ($waybill) {
                                $sign_dir = Yii::$app->params['sign_dir'] . date('Y/m/d', $waybill->created_at) . '/' . $waybill->id . '/';
                                if (!is_dir($sign_dir)) {
                                    FileHelper::createDirectory($sign_dir);
                                }

                                $oldStatus = $waybill->status;

                                $sign_file_name = 'pickup_sign.png';
                                if ($this->is_delivery) {
                                    $sign_file_name = 'delivery_sign.png';
                                }

                                if ($waybill->status == 1) {
                                    $sign_file_name = 'pickup_sign.png';
                                }

                                if ($waybill->status >= 9) {
                                    $sign_file_name = 'delivery_sign.png';
                                }

                                if ($waybill->cod_taken) {
                                    $sign_file_name = 'given_money_sign.png';
                                    $waybill->cod_taken = 0;
                                }

                                if ($this->sign_file->saveAs($sign_dir . $sign_file_name, false)) {
                                    $history = new History();
                                    $history->waybill_id = $waybill->id;
                                    $history->user_id = $identity->id;
                                    $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ga elektron imzo saqlandi';
                                    $history->message_en = $identity->fullname . ' saved electron sign for ' . $waybill->barcode;
                                    $history->message_ru = $identity->fullname . ' сохранил(а) электрон подпись для ' . $waybill->barcode;
                                    $history->save();
                                }

                                if ($this->photo) {
                                    if ($this->photo->saveAs($sign_dir . 'photo.png', false)) {
                                        $history = new History();
                                        $history->waybill_id = $waybill->id;
                                        $history->user_id = $identity->id;
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ga rasm-hisobot saqlandi';
                                        $history->message_en = $identity->fullname . ' saved photo report for ' . $waybill->barcode;
                                        $history->message_ru = $identity->fullname . ' сохранил(а) электрон фотоотчет для ' . $waybill->barcode;
                                        $history->save();

                                        // if (in_array($waybill->from_client_id, [504,684])) {
                                        //     $handoverPre = HandoverPre::findOne(['waybill_id' => $waybill->id]);
                                        //     if ($handoverPre) {
                                        //         if ($handoverPre->status == 1) {
                                        //             $handoverPre->status = 2;
                                        //             $handoverPre->save(false);
                                        //         }
                                        //     } else {
                                        //         $_r = Yii::$app->db->createCommand()->insert(HandoverPre::tableName(), ['waybill_id' => $waybill->id, 'barcode' => $waybill->barcode, 'status' => 2])->execute();
                                        //     }
                                        // }
                                    }
                                }

                                $oldStatus = $waybill->status;

                                if ($this->is_delivery) {
                                    if (in_array($waybill->from_client_id, [504,684,3])) {
                                        if ($waybill->status == 10) {
                                            $waybill->status = 57;
                                            $this->msg .= "status=57;";
                                        } else {
                                            $waybill->status = 10;
                                            $this->msg .= "status=10;";
                                            // $oldStatus = 3;
                                            $waybill->delivery_courier_id = $identity->id;
                                        }
                                    } else {
                                        $waybill->status = 11;
                                    }
                                    $waybill->old_status = $oldStatus;
                                }
                                if (in_array($oldStatus, [1,2]) && !in_array($waybill->from_client_id, [504,684,3])) {
                                    $waybill->status = 3;
                                    $this->msg .= "status=3;";
                                    $waybill->old_status = $oldStatus;
                                    $waybill->pickup_courier_id = $identity->id;
					$waybill->delivery_courier_id = $identity->id;
                                }
                                if ($this->datetime) {
                                    if ($this->is_delivery) {
                                        $waybill->delivery_datetime = $this->datetime;
                                    } else {
                                        $waybill->pickup_datetime = $this->datetime;
                                    }
                                }
                                $waybill->for_sign = 0;
                                if ($waybill->save(false)) {
                                    
                                    $history = new History();
                                    $history->waybill_id = $waybill->id;
                                    $history->user_id = $identity->id;
                                    if (in_array($oldStatus, [1,2])) {
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ni ' . $this->full_name .' dan qabul qilib oldi';
                                        $history->message_en = $identity->fullname . ' picked up ' . $waybill->barcode . ' from sender ' . $this->full_name;
                                        $history->message_ru = $identity->fullname . ' забрал(а) ' . $waybill->barcode . ' у отправителя ' . $this->full_name;
                                    } else {
                                        $history->message_uz = $identity->fullname . ' ' . $waybill->barcode . ' ni ' . $this->full_name .' ga yetkazdi';
                                        $history->message_en = $identity->fullname . ' delivered ' . $waybill->barcode . ' to ' . $this->full_name;
                                        $history->message_ru = $identity->fullname . ' доставил(а) ' . $waybill->barcode . ' в ' . $this->full_name;
                                    }
                                    $history->save();

                                    if ($oldStatus !== 11 && !in_array($oldStatus, [1,2])) {
                                        $region = Region::findOne($waybill->to_region_id);
                                        $to_name = $waybill->to_fullname;
                                        if ($region) {
                                            $to_name .= "(".$region->short_name.")";
                                        }
                                        $from_phone_number = $waybill->from_phone_number;
                                        $from_phone_number = str_replace(' ', '', $from_phone_number);
                                        $message = "AKTIV: Uvajayemiy klient, Vasha pochta kod " . $waybill->barcode . " dostavlen/Hurmatli mijoz, pochtangiz ". $to_name ." ga yetkazildi.";
                                        if (strlen($message) >= 126) {
                                            $message = "AKTIV: Uvajayemiy klient, Vasha pochta kod " . $waybill->barcode . " dostavlen/Pochtangiz ". $to_name ." ga yetkazildi.";
                                        }
                                        Yii::$app->smscomponent->send($from_phone_number, $message, $waybill->id);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
