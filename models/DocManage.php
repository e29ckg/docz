<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_manage".
 *
 * @property int $id
 * @property string $doc_form
 * @property int $user_id
 * @property string $name
 * @property int $role_name_id
 * @property int|null $sort
 * @property string|null $detail
 * @property string $created
 */
class DocManage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_manage';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_form', 'user_id', 'name', 'role_name_id'], 'required'],
            [['user_id', 'role_name_id', 'sort'], 'integer'],
            [['detail'], 'string'],
            [['created'], 'safe'],
            [['doc_form', 'name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'doc_form' => 'Doc Form',
            'user_id' => 'User ID',
            'name' => 'Name',
            'role_name_id' => 'Role Name ID',
            'sort' => 'Sort',
            'detail' => 'Detail',
            'created' => 'Created',
        ];
    }
}
