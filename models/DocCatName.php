<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_cat_name".
 *
 * @property int $id
 * @property string $name
 * @property string|null $note
 */
class DocCatName extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_cat_name';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name', 'note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'ชื่อแฟ้ม',
            'note' => 'Note',
        ];
    }
}
