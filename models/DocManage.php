<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_manage".
 *
 * @property int $id
 * @property string $doc_form
 * @property int $doc_id
 * @property int $role_name_id
 * @property int $user_id
 * @property int|null $sort
 * @property string|null $detail
 * @property int|null $st
 * @property string|null $updated
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
            [['doc_form', 'doc_id', 'role_name_id', 'user_id'], 'required'],
            [['doc_id', 'role_name_id', 'user_id', 'sort', 'st'], 'integer'],
            [['detail','ty'], 'string'],
            [['updated', 'created'], 'safe'],
            [['doc_form'], 'string', 'max' => 255],
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
            'doc_id' => 'Doc ID',
            'role_name_id' => 'Role Name ID',
            'user_id' => 'User ID',
            'sort' => 'Sort',
            'ty' => 'ตรายาง',
            'detail' => 'รายละเอียด..',
            'st' => 'สถานะ',
            'updated' => 'Updated',
            'created' => 'Created',
        ];
    }
    public function getRole_name()
    {
        return $this->hasOne(RoleName::className(), ['id' => 'role_name_id']);
    }

    public function getRole_power()
    {
        return $this->hasMany(RolePower::className(), ['role_name_id' => 'role_name_id']);
    }

    public function role_name()
    {
        return $this->role_name->name ;
    }

    public function getDocz()
    {
        return $this->hasOne(Docz::className(), ['id' => 'doc_id']);
    }
    public function getProfile()
    {
        return $this->hasOne(UserProfile::className(), ['user_id' => 'user_id']);
    }
    public function username()
    {
        return $this->profile->pfname.$this->profile->name.' '.$this->profile->sname;
    }
    
    public function url_file()
    {
        return $this->docz->file;
    }

    public function getDoc_file()
    {
        return $this->hasMany(DocFile::className(), ['id'=>'doc_id'])
                    ->orderBy(['id' => SORT_ASC]);
    }   



}
