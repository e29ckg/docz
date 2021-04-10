<?php

use yii\db\Migration;

/**
 * Class m210329_230452_create_profile
 */
class m210329_230452_create_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_profile', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'pfname' => $this->string(255),
            'name' => $this->string(255)->notNull(),
            'sname' => $this->string(255),
            'dep_name' => $this->string(255),
            'group_work' => $this->string(255),
            'role' => $this->string(255),
            'photo' => $this->string(255),
            'sign_photo' => $this->string(255),
            'created_at' => $this->timestamp()
        ]);
        $this->createTable('user_group_work', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'sort' => $this->integer(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_group_work', ['name' => 'กลุ่มช่วยอำนวยการ','sort' => 1]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานคดี','sort' => 2]);

        $this->createTable('user_dep_name', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'sort' => $this->integer(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_dep_name', ['name' => 'พนักงานคอมพิวเตอร์','sort' => 1]);
        $this->insert('user_dep_name', ['name' => 'เจ้าพนักงานศาลยุติธรรม','sort' => 2]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_230452_create_profile cannot be reverted.\n";
        $this->dropTable('user_profile');
        $this->dropTable('user_group_work');
        $this->dropTable('user_dep_name');
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210329_230452_create_profile cannot be reverted.\n";

        return false;
    }
    */
}
