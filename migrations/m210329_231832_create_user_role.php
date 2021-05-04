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
        $this->createTable('role', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'role_name_id' => $this->integer()->notNull(),
            'name_dep1' => $this->string(255),
            'name_dep2' => $this->string(255),
            'name_dep3' => $this->string(255),
        ]);
        $this->insert('role', ['user_id' => 1,'role_name_id' => 1,'name_dep1'=>'พนักงานคอมพิวเตอร์']);

        $this->createTable('role_name', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'sort' => $this->integer(),
            'status' => $this->boolean()->defaultValue(1),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('role_name', ['name' => 'เจ้าหน้าที่สารบรรณ','sort' => 1]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานสารบรรณ','sort' => 2]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานคดี','sort' => 3]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานช่วยพิจารณา','sort' => 4]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานคลัง','sort' => 5]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานประชาสัมพันธ์','sort' => 6]);
        $this->insert('role_name', ['name' => 'หัวหน้ากลุ่มงานไกล่เกลี่ย','sort' => 7]);
        $this->insert('role_name', ['name' => 'ผู้อำนวยการฯ','sort' => 8]);
        $this->insert('role_name', ['name' => 'ผู้พิพากษาหัวหน้าศาลฯ','sort' => 9]);

        $this->createTable('role_power', [
            'id' => $this->primaryKey(),
            'role_name_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull()
        ]);
        $this->insert('role_power', ['role_name_id' => 1,'user_id'=>1]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('role');
        $this->dropTable('role_name');
        $this->dropTable('role_power');
        return false;
    }

}
