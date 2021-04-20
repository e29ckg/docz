<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_file".
 *
 * @property int $id
 * @property int|null $docz_id
 * @property string $doc_form
 * @property string $name
 * @property string|null $file
 * @property string|null $ext
 * @property int $user_id_create
 * @property string $created
 */
class DocFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['docz_id', 'user_id_create'], 'integer'],
            [['doc_form', 'name', 'user_id_create'], 'required'],
            [['created'], 'safe'],
            [['doc_form', 'name', 'file', 'ext'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'docz_id' => 'Docz ID',
            'doc_form' => 'Doc Form',
            'name' => 'Name',
            'file' => 'File',
            'ext' => 'Ext',
            'user_id_create' => 'User Id Create',
            'created' => 'Created',
        ];
    }
}
