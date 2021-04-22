<?php

namespace app\models;
use Yii;

/**
 * This is the model class for table "role_power".
 *
 * @property int $id
 * @property int $role_name_id
 * @property int $user_id
 */
class RolePower extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'role_power';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['role_name_id', 'user_id'], 'required'],
            [['role_name_id', 'user_id'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'role_name_id' => 'หน้าที่',
            'user_id' => 'ผู้รับผิดชอบ',
        ];
    }

    public function getRole_name()
    {
        return $this->hasOne(RoleName::className(), ['id' => 'role_name_id']);
    }
    public function role_name()
    {
        return $this->role_name->name;
    }

    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    // public function getName()
    // {
    //     return $this->profile->pfname.$this->profile->name.' '.$this->profile->sname;
    // }

}
