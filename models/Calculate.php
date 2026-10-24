<?php

namespace app\models;

use Yii;
use yii\base\Model;
use app\models\Waybill;

/**
 * 
 *
 *
 */
class Calculate extends Model
{
	public $sended_date;
	public $from_city_id;
	public $to_city_id;
	public $from_client_id;
	public $from_individual_id;
	public $discount;
	public $weight;
	public $quantity;
	public $is_delivery;
	public $call_courier;
	public $transport_type;
	public $is_return_money;
	public $parent_id;
	public $is_cod;

	/**
	* @return array the validation rules.
	*/
	public function rules()
	{
		return [
			[['from_city_id', 'to_city_id', 'weight', 'quantity'], 'required'],
			[['from_individual_id', 'from_city_id', 'to_city_id', 'quantity', 'from_client_id', 'is_delivery', 'call_courier', 'transport_type', 'is_return_money', 'parent_id', 'is_cod'], 'integer'],
			[['weight', 'discount'], 'number'],
			[['sended_date'], 'safe']
		];
	}

	public function calc()
	{
		$call_price = 0;
		$first_price = 0;
		$weight_price = 0;
		$delivery_price = 0;
		$call_far_price = 0;
		$delivery_far_price = 0;
		$far_price = 0;
		$every_price = 0;
		$discount = 0;
		$check_call = 0;
		$check_delivery = 0;

		$from_office = 0;
		$to_office = 0;

		$has_far_call = 0;
		$has_far_delivery = 0;

		$plan_id = null;
		$price_id = null;

		$parent_id = $this->parent_id;
		$from_individual_id = $this->from_individual_id;
		$from_city_id = $this->from_city_id;
		$to_city_id = $this->to_city_id;
		$weight = $this->weight ? $this->weight*1 : 0.5;
		$quantity = $this->quantity ? $this->quantity : 1;
		$is_delivery = $this->is_delivery;
		$call_courier = $this->call_courier;
		$transport_type = $this->transport_type ? 'air':'car';
		$discount = $this->discount ? $this->discount : 0;
		$is_return_money = $this->is_return_money ? $this->is_return_money : 0;
		$is_cod = $this->is_cod ? $this->is_cod : 0;
		$sended_date = $this->sended_date ? $this->sended_date : '2019-11-01';

		$price = null;
		$prices = null;
		$client = null;

		$condition = [
			'type' => $transport_type
		];
		$_weight = ceil($weight);


		if ($is_return_money && $parent_id) {
			if ($parent_waybill = Waybill::findOne($parent_id)) {
				$price_id = $parent_waybill->price_id?:null;
			}
		}

		if ($this->from_client_id) {

			if ($this->from_client_id == 10) {
				return [
					'total_price' => 0,
					'price' => 0,
					'discount' => 0,
					'call_far_price' => 0,
					'call_price' => 0,
					'first_price' => 0,
					'weight_price' => 0,
					'delivery_price' => 0,
					'delivery_far_price' => 0,
					'from_office' => 0,
					'to_office' => 0,
					'has_far_call' => 0,
					'has_far_delivery' => 0,
					'plan_id' => null,
					'price_id' => $price_id
				];
			}

			$client = Client::findOne(['id' => $this->from_client_id]);
			if ($client) {
				$condition['client_id'] = $client->id;
				$condition['contract_id'] = $client->contract_id;
			}
		}

		if ($client) {

		} else {
			if ($sended_date <= '2020-04-14') {

			} elseif ($sended_date >= '2020-04-14' && $sended_date < '2020-05-27') {
				$condition['plan_id'] = 2;
			} else {
				$condition['plan_id'] = 3;
			}
		}

		$from_city = City::findOne($from_city_id);
		$to_city = City::findOne($to_city_id);

		if ($from_city && $to_city && $to_city->region_id == $from_city->region_id) {
			$condition['type'] = 'city';
			$price = Prices::find()
				->where($condition)
				->andWhere(['<', 'from_weight', $weight])
				->andWhere(['>=', 'to_weight', $weight])
				->one();
			if (!$price) {
				$price = Prices::find()
					->where($condition)
					->andWhere(['<', 'from_weight', $weight])
					->andWhere(['>=', 'to_weight', $weight])
					->one();
			}
		}
		if (!$price) {
			$price = Prices::find()
			->where($condition)
			->andWhere(['<', 'from_weight', $weight])
			->andWhere(['>=', 'to_weight', $weight])
			->one();
		}

		if (!$price) {
			$price = Prices::find()
			->where(['type' => $transport_type])
			->andWhere(['<', 'from_weight', $weight])
			->andWhere(['>=', 'to_weight', $weight])
			->one();
		}

		if ($is_return_money) {
			return [
				'total_price' => 0,
				'price' => 0,
				'discount' => 0,
				'call_far_price' => 0,
				'call_price' => 0,
				'first_price' => 0,
				'weight_price' => 0,
				'delivery_price' => 0,
				'delivery_far_price' => 0,
				'from_office' => 0,
				'to_office' => 0,
				'has_far_call' => 0,
				'has_far_delivery' => 0,
				'plan_id' => null,
				'price_id' => $price?$price->id:null
			];
		}

		if ($price) {
			$price_id = $price->id;

			if ($from_city && $to_city) {
				$from_office = $from_city->near_office;
				$to_office = $to_city->near_office;

				if ($from_office && !$call_courier) {
					$call_price = 0;
					$call_far_price = 0;
				} elseif ($from_office && $call_courier) {
					$call_price = $price->call_courier;
					$call_far_price = 0;
				} elseif (!$from_office && $call_courier) {
					$call_price = $price->call_courier;
					$call_far_price = $price->call_courier;
				} else {
					$call_price = $price->call_courier;
					$call_far_price = $price->call_courier;
					$has_far_call = 1;
				}
				
				if ($to_office && !$is_delivery) {
					$delivery_price = 0;
					$delivery_far_price = 0;
				} elseif ($to_office && $is_delivery) {
					$delivery_price = $price->delivery_courier;
					$delivery_far_price = 0;
				} elseif (!$to_office && $is_delivery) {
					$delivery_price = $price->delivery_courier;
					$delivery_far_price = $price->delivery_price;
				} else {
					$delivery_price = $price->delivery_courier;
					$delivery_far_price = $price->delivery_price;
					$has_far_delivery = 1;
				}
			}

			if ($to_city && $to_city->region_id == 14) {
				$delivery_far_price = 0;
			}

			if ($to_city && $to_city->region_id == 11) {
				// $delivery_far_price = 0;
			}

			if ($_weight > 1) {
				$weight_price = $price->prev_price + $price->weight_price * ($_weight - $price->from_weight);
			} else {
				$first_price = $price->first_price*1;
				if ($client && $client->is_online_store) {
					if ($client->is_own_price) {
						
					} else {
						if ($sended_date >= '2022-10-02') {
							$first_price = 16000;
						}
					}
				}

				if (($price->type == 'city') && ($call_courier && $is_delivery)) {
					$first_price = 0;
				} elseif (($price->type == 'city') && ($call_courier || $is_delivery)) {
					$first_price = 9000;
				} elseif (($price->type == 'city') && (!$call_courier && !$is_delivery)) {
					$first_price = 9000;
				}
			}
		}

		if ($is_cod && !$this->from_client_id) {
			if ($_weight <= 1) {
				$first_price = 9000;
			}
		}

		$price = $call_far_price + $call_price + $first_price + $weight_price + $delivery_price + $delivery_far_price;
		$total_price = $price - $discount;

		if ($client && $client->id == 684) {
			$total_price = 30000;
		}

		return [
			'weight' => $_weight,
			'total_price' => $total_price,
			'price' => $price,
			'discount' => $discount,
			'call_far_price' => $call_far_price,
			'call_price' => $call_price,
			'first_price' => $first_price,
			'weight_price' => $weight_price,
			'delivery_price' => $delivery_price,
			'delivery_far_price' => $delivery_far_price,
			'from_office' => $from_office,
			'to_office' => $to_office,
			'has_far_call' => $has_far_call,
			'has_far_delivery' => $has_far_delivery,
			'plan_id' => $plan_id,
			'price_id' => $price_id
		];
	}
}
