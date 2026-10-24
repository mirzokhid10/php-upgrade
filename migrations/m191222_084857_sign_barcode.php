<?php

use yii\db\Migration;

/**
 * Class m191222_084857_sign_barcode
 */
class m191222_084857_sign_barcode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sign', 'barcode', $this->string()->after('waybill_id'));
        $this->addColumn('sign', 'barcodes', $this->string()->after('barcode'));

        $this->createIndex('ix_sign_barcode', 'sign', 'barcode');
        $this->createIndex('ix_sign_barcodes', 'sign', 'barcodes');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m191222_084857_sign_barcode cannot be reverted.\n";

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191222_084857_sign_barcode cannot be reverted.\n";

        return false;
    }
    */
}
