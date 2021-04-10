<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user_profile".
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $pfname
 * @property string $name
 * @property string|null $sname
 * @property string|null $dep_name
 * @property string|null $group_work
 * @property string|null $photo
 * @property string|null $sign_photo
 * @property string $created_at
 */
class UserProfile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_profile';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'name'], 'required'],
            ['user_id', 'unique', 'targetClass' => '\app\models\UserProfile', 'message' => 'This User_id has already been taken.'],
            
            [['user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['pfname', 'name', 'sname', 'dep_name', 'group_work', 'photo', 'sign_photo'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'pfname' => 'Pfname',
            'name' => 'Name',
            'sname' => 'Sname',
            'dep_name' => 'Dep Name',
            'group_work' => 'Group Work',
            'photo' => 'Photo',
            'sign_photo' => 'Sign Photo',
            'created_at' => 'Created At',
        ];
    }
}
