<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user_group_work".
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $sort
 * @property string $created_at
 */
class UserGroupWork extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_group_work';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sort'], 'integer'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'sort' => 'Sort',
            'created_at' => 'Created At',
        ];
    }
}
