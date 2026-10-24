<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%calling_courier_request}}`.
 */
class m260701_124022_create_calling_courier_request_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%calling_courier_request}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'city_id' => $this->integer()->notNull(),
            'region_id' => $this->integer(),
            'phone' => $this->string(20)->notNull(),
            'address' => $this->string(255),
            'latitude' => $this->decimal(10, 8)->null(),
            'longitude' => $this->decimal(11, 8)->null(),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);
        $this->createIndex('idx_calling_courier_request_user_id', '{{%calling_courier_request}}', 'user_id');
        $this->createIndex('idx_calling_courier_request_city_id', '{{%calling_courier_request}}', 'city_id');
        $this->createIndex('idx_calling_courier_request_region_id', '{{%calling_courier_request}}', 'region_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%calling_courier_request}}');
    }
}
