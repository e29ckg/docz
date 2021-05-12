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
    public function getRole()
    {
        return $this->hasOne(Role::className(), ['role_name_id' => 'role_name_id','user_id'=>'user_id']);
    }

    public function role_name_dep($user_id,$role_name_id)
    {
        $role_count = Role::find()->where(['role_name_id' => $role_name_id,'user_id'=>$user_id])->count();
        $name_dep = '';
        if($role_count > 0){
            $role = Role::find()->where(['role_name_id' => $role_name_id,'user_id'=>$user_id])->count();
            $name_dep = $role->name_dep1 ? $role->name_dep1 : '';
            $name_dep .= $role->name_dep2 ? $role->name_dep2 : '';
            $name_dep .= $role->name_dep3 ? $role->name_dep3 : '';

        }
        return $name_dep;
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
    public function name_doc(){
        $name = $this->docz->doc_speed ? '<small class="label  bg-red">'.$this->docz->doc_speed.'</small>':'';
        $name .=$this->docz->doc_form_number ? 'ที่ '.$this->docz->doc_form_number : '';
        $name .=$this->docz->doc_date ? 'ลงวันที่ '.date("Y-m-d",strtotime($this->docz->doc_date)) : '';
        $name .= $this->docz->name ? 'เรื่อง '.$this->docz->name : '';
        return $name;
    }

    public function getProfile()
    {
        return $this->hasOne(UserProfile::className(), ['user_id' => 'user_id']);
    }
    
    public function username()
    {
        return $this->profile->pfname.$this->profile->name.' '.$this->profile->sname;
    }
    public function dep_name()
    {
        return $this->profile->dep_name;
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
