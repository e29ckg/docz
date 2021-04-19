<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_doc_file extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('doc_file', [
            'id' => $this->primaryKey(),
            'doc_form' => $this->string(255)->notNull(),
            'name' => $this->string(255)->notNull(),
            'file' => $this->string(255),
            'ext' => $this->string(255),            
            'user_id_create' => $this->integer()->notNull(),
            'created'=> $this->timestamp()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('doc_file');
        return false;
    }
}
