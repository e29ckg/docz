<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_docz extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('docz', [
            'id' => $this->primaryKey(),            
            'r_number' => $this->string()->notNull(),
            'r_date' => $this->dateTime(),
            'doc_speed' =>$this->string(),
            'doc_form_number' => $this->string(),
            'doc_date' => $this->dateTime(),
            'doc_form' => $this->string(),            
            'doc_to' => $this->string()->notNull(),
            'name' => $this->string(1000)->notNull(),
            'file' => $this->string(255),
            'user_create' => $this->integer(),            
            'st'=> $this->integer()->defaultValue(1),
            'start'=> $this->dateTime(),
            'end'=> $this->dateTime(),
            'created'=> $this->timestamp(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('docz');
        return false;
    }
}
