<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_doc_user_read extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('doc_user_read', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer(),
            'doc_id' => $this->integer(),            
            'ckeck' => $this->integer(),
            'ip' => $this->string(),
            'updated' => $this->dateTime(),
            'created' => $this->dateTime(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('doc_user_read');
        return false;
    }
}
