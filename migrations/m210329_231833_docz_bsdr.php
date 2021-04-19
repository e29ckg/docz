<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_docz_bsdr extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('docz_bsdr', [
            'id' => $this->primaryKey(),
            'doc_date' => $this->string()->notNull(),
            'name' => $this->string(255)->notNull(),
            'bsdr_file' => $this->string(255),
            'created'=> $this->timestamp()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('docz_bsdr');
        return false;
    }
}
