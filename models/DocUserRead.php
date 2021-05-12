<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_user_read".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $doc_id
 * @property int|null $check
 * @property string|null $ip
 * @property string|null $updated
 * @property string|null $created
 */
class DocUserRead extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_user_read';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_id', 'check'], 'integer'],
            [['user_id','updated', 'created'], 'safe'],
            [['ip'], 'string', 'max' => 255],
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
            'doc_id' => 'Doc ID',
            'check' => 'Check',
            'ip' => 'Ip',
            'updated' => 'Updated',
            'created' => 'Created',
        ];
    }
    public function getDocz()
    {
        return $this->hasOne(Docz::className(), ['id'=>'doc_id']);
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

    public function doc_un_read_count(){
        return DocUserRead::find()->select('id')->where(['check'=>0,'user_id'=>Yii::$app->user->id])->count();
    }

    public function LineToken($user_id){
        $token = UserProfile::find()->where(['user_id' => $user_id])->one();
        return $token;
    }
}
