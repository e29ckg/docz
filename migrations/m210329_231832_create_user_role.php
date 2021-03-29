<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231832_create_user_role extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_role', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'role_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'created_at' => $this->timestamp(),
        ]);
        $this->createTable('user_role_name', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'sort' => $this->integer(),
            'description' => $this->text(),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_role_name', ['name' => 'เจ้าหน้าที่ทั่วไป','sort' => 1]);
        $this->insert('user_role_name', ['name' => 'หัวหน้ากลุ่มงาน','sort' => 2]);
        $this->insert('user_role_name', ['name' => 'ผู้อำนวยการ','sort' => 3]);
        $this->insert('user_role_name', ['name' => 'ผู้พิพากษาหัวหน้าศาล','sort' => 4]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('user_role');
        $this->dropTable('user_role_name');
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";

        return false;
    }
    */
}
