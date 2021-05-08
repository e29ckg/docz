<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_cat".
 *
 * @property int $id
 * @property int|null $doc_id
 * @property int|null $doc_cat_name_id
 * @property string|null $note
 */
class DocCat extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_cat';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_id', 'doc_cat_name_id'], 'integer'],
            [['note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'doc_id' => 'Doc ID',
            'doc_cat_name_id' => 'Doc Cat Name ID',
            'note' => 'Note',
        ];
    }

    public function getDocz()
    {
        return $this->hasOne(DocZ::className(), ['id'=>'doc_id']);
    }

}
