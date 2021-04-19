<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_doc_manage extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('doc_manage', [
            'id' => $this->primaryKey(),
            'doc_form' => $this->string(255)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'role_name_id' => $this->integer()->notNull(),
            'sort' => $this->integer(),
            'detail' => $this->text(),            
            'created'=> $this->timestamp()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_ cannot be reverted.\n";
        $this->dropTable('doc_manage');
        return false;
    }
}
