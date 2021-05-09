<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;
use yii\helpers\Url;
/**
 * This is the model class for table "user_profile".
 *
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
            
            [['user_id','sort'], 'integer'],
            [['created_at'], 'safe'],
            [['pfname', 'name', 'sname', 'dep_name', 'group_work','phone','line_id'], 'string', 'max' => 255],
            [['photo','sign_photo'], 'file', 'extensions' => 'png, jpg'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'user_id' => 'User ID',
            'pfname' => 'คำนำหน้า',
            'name' => 'ชื่อ',
            'sname' => 'นามสกุล',
            'dep_name' => 'ตำแหน่ง',
            'group_work' => 'กลุ่มงาน',
            'line_id' => 'Line Token',
            'photo' => 'Photo',
            'sign_photo' => 'Sign Photo',
            'sort' => 'ลำดับ',
            'created_at' => 'Created At',
        ];
    }
    public function image($photo){
        if(!$photo ==''  && file_exists(Url::to('@webroot/'.$photo))){
           return Url::to('@web/'.$photo);
        }
        return Url::to(Url::to('@web/img/user2-160x160.jpg'));
    }
    public function getname()
    {
        return $this->pfname.$this->name.' '.$this->sname;
    }
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
    public function status()    {
        return $this->user->status;
    }

}
