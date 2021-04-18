<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "role".
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_name_id
 * @property string|null $name_dep1
 * @property string|null $name_dep2
 * @property string|null $name_dep3
 */
class Role extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'role';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'role_name_id'], 'required'],
            [['user_id', 'role_name_id'], 'integer'],
            [['name_dep1', 'name_dep2', 'name_dep3'], 'string', 'max' => 255],
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
            'role_name_id' => 'สิทธ์ในการลงนาม',
            'name_dep1' => 'บรรทัดที่ 1',
            'name_dep2' => 'บรรทัดที่ 2',
            'name_dep3' => 'บรรทัดที่ 3',
        ];
    }
    public function getRole_name()
    {
        return $this->hasOne(RoleName::className(), ['id' => 'role_name_id']);
    }
}
