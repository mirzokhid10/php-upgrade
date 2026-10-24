<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%app_version}}`.
 */
class m200129_091535_create_app_version_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%app_version}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(),
            'number' => $this->string(),
            'status' => $this->smallInteger()->defaultValue(0),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%app_version}}');
    }
}
