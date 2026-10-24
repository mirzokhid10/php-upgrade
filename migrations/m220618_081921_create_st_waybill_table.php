<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%st_waybill}}`.
 */
class m220618_081921_create_st_waybill_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->createTable('{{%st_waybill}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->unsigned()->comment('User'),
            'tracking_number' => $this->string()->comment('Tracking number'),
            'first_name' => $this->string()->comment('First name'),
            'last_name' => $this->string()->comment('Last name'),
            'weight' => $this->decimal(20,2)->comment('Weight'),
            'is_liquid' => $this->boolean()->unsigned()->comment('Liquid'),
            'is_magnetic' => $this->boolean()->unsigned()->comment('Magnetic'),
            'is_breakable' => $this->boolean()->unsigned()->comment('Breakable'),
            'is_electronic' => $this->boolean()->unsigned()->comment('Electronic'),
            'price' => $this->decimal(20,2)->comment('Price'),
            'currency_code' => $this->string()->comment('Currency code'),
            'total_price' => $this->decimal(20,2)->comment('Total price'),
            'shipping_price' => $this->decimal(20,2)->comment('Shipping price'),
            'address' => $this->string()->comment('Address'),
        ], $tableOptions);

        $this->createIndex(
            'idx-st_waybill-tracking_number',
            'st_waybill',
            'tracking_number'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m190918_073822_init cannot be reverted.\n";

        return false;
    }
}
