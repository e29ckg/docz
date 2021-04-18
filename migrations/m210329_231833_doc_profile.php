<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_doc_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('doc_profile', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
        ]);
        $this->insert('doc_profile', ['id'=>1,'name' => 'รายงานวันทำการประจำวัน']);
        $this->insert('doc_profile', ['id'=>2,'name' => 'หนังสือเวียน']);

        $this->createTable('doc_profile_sub', [
            'id' => $this->primaryKey(),
            'doc_profile_id' => $this->string(255)->notNull(),
            'role_name_id' => $this->integer()->notNull(),
            'sort' => $this->integer(),
        ]);

        $this->insert('doc_profile_sub', ['doc_profile_id' => 1,'role_name_id'=>1,'sort' => 1]);
        $this->insert('doc_profile_sub', ['doc_profile_id' => 1,'role_name_id'=>2,'sort' => 1]);
        $this->insert('doc_profile_sub', ['doc_profile_id' => 1,'role_name_id'=>8,'sort' => 1]);
        $this->insert('doc_profile_sub', ['doc_profile_id' => 1,'role_name_id'=>9,'sort' => 1]);        

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('doc_profile');
        $this->dropTable('doc_profile_sub');
        return false;
    }
}
