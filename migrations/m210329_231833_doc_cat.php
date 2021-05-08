<?php

use yii\db\Migration;

/**
 * Class m210329_231832_create_user_role
 */
class m210329_231833_doc_cat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('doc_cat', [
            'id' => $this->primaryKey(),
            'doc_id' => $this->integer(),
            'name' => $this->string(255)->notNull(),
            'note' => $this->string(255),
        ]);
        $this->createTable('doc_cat_name', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'note' => $this->string(255),
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210329_231832_create_user_role cannot be reverted.\n";
        $this->dropTable('doc_cat');
        $this->dropTable('doc_cat_name');
        return false;
    }
}
