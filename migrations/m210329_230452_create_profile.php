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
            'user_id' => $this->primaryKey(),
            'pfname' => $this->string(255),
            'name' => $this->string(255)->notNull(),
            'sname' => $this->string(255),
            'dep_name' => $this->string(255),
            'group_work' => $this->string(255),
            'role' => $this->string(255),
            'phone' => $this->string(255),
            'line_id' => $this->string(255),            
            'photo' => $this->string(255),
            'sort' => $this->integer(11)->defaultValue(0),
            'sign_photo' => $this->string(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_profile', ['user_id' => 1,'name' => 'admin']);

        $this->createTable('profile_pfname', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'sort' => $this->integer(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('profile_pfname', ['name' => 'นาย','sort' => 1]);
        $this->insert('profile_pfname', ['name' => 'นางสาว','sort' => 2]);
        $this->insert('profile_pfname', ['name' => 'นาง','sort' => 3]);

        $this->createTable('user_group_work', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'sort' => $this->integer(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_group_work', ['name' => 'กลุ่มช่วยอำนวยการ','sort' => 1]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานคดี','sort' => 2]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานช่วยพิจารณาคดี','sort' => 3]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานคลัง','sort' => 4]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานปริการประชาชนและประชาสัมพันธ์','sort' => 5]);
        $this->insert('user_group_work', ['name' => 'กลุ่มงานไกล่เกลี่ยและประนอมข้อพิพาท','sort' => 6]);

        $this->createTable('user_dep_name', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255),
            'sort' => $this->integer(255),
            'created_at' => $this->timestamp()
        ]);
        $this->insert('user_dep_name', ['name' => 'ผู้พิพากษาหัวหน้าศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์','sort' => 1]);
        $this->insert('user_dep_name', ['name' => 'ผู้พิพากษา','sort' => 2]);
        $this->insert('user_dep_name', ['name' => 'ผู้อำนวยการฯ','sort' => 3]);
        $this->insert('user_dep_name', ['name' => 'เจ้าพนักงานศาลยุติธรรมชำนาญการพิเศษ','sort' => 4]);
        $this->insert('user_dep_name', ['name' => 'เจ้าพนักงานศาลยุติธรรมชำนาญการ','sort' => 5]);
        $this->insert('user_dep_name', ['name' => 'เจ้าพนักงานศาลยุติธรรมปฏิบัติการ','sort' => 6]);
        $this->insert('user_dep_name', ['name' => 'นิติกรชำนาญการพิเศษ','sort' => 7]);
        $this->insert('user_dep_name', ['name' => 'นิติกรชำนาญการ','sort' => 8]);        
        $this->insert('user_dep_name', ['name' => 'นักจิตวิทยาชำนาญการ','sort' => 9]);
        $this->insert('user_dep_name', ['name' => 'นักจิตวิทยาปฏิบัติการ','sort' => 10]);        
        $this->insert('user_dep_name', ['name' => 'นักวิชาการเงินและบัญชีปฏิบัติการ','sort' => 11]);
        $this->insert('user_dep_name', ['name' => 'เจ้าหน้าที่ศาลยุติธรรมชำนาญงาน','sort' => 12]);
        $this->insert('user_dep_name', ['name' => 'เจ้าหน้าที่ศาลยุติธรรมปฏิบัติงาน','sort' => 13]);
        $this->insert('user_dep_name', ['name' => 'เจ้าพนักงานการเงินและบัญชี','sort' => 14]);
        $this->insert('user_dep_name', ['name' => 'พนักงานคอมพิวเตอร์','sort' => 15]);
        $this->insert('user_dep_name', ['name' => 'พนักงานสถานที่','sort' => 16]);
        $this->insert('user_dep_name', ['name' => 'นิติกร','sort' => 17]);
        $this->insert('user_dep_name', ['name' => 'พนักงานขับรถยนต์','sort' => 18]);        
        
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

    
}
