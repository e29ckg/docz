<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "role_name".
 *
 * @property int $id
 * @property string $name
 * @property int|null $sort
 * @property string|null $description
 * @property string $created_at
 */
class RoleName extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'role_name';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['sort'], 'integer'],
            [['status'], 'boolean'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            ['name', 'unique', 'targetClass' => '\app\models\RoleName', 'message' => 'This name has already been taken.'],
           
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'ชื่อสิทธิ์',
            'sort' => 'Sort',
            'status' => 'สถานะ',
            'created_at' => 'Created At',
        ];
    }
}
