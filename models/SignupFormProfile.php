<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\User;
use app\models\UserProfile;

/**
 * Signup form
 */
class SignupFormProfile extends Model
{
    public $username;
    public $email;
    public $password;
    public $rpassword;
    public $pfname;
    public $name;
    public $sname;
    public $dep;
    public $dep_name;
    public $group_work;
    public $phone;
    public $line_id;
    public $photo;
    public $sign_photo;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['username', 'trim'],
            ['username', 'required'],
            ['username', 'unique', 'targetClass' => '\app\models\User', 'message' => 'มีผู้ใช้ชื่อนี้แล้ว.'],
            ['username', 'string', 'min' => 2, 'max' => 255],

            ['email', 'trim'],
            // ['email', 'required'],
            // ['email', 'email'],
            // ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => '\app\models\User', 'message' => 'email นี้มีผู้ใช้แล้ว.'],

            [['password','rpassword'], 'required'],
            ['rpassword', 'compare', 'compareAttribute' => 'password'],
            ['password', 'string', 'min' => Yii::$app->params['user.passwordMinLength']],
            
            ['name', 'required'],
            [['pfname','sname','dep_name','group_work'], 'string'],

        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => 'Username',
            'password' => 'Password',
            'rpassword' => 'Retype Password',
            'pfname' => 'คำนำหน้าชื่อ',
            'name' => 'ชื่อ',
            'sname' => 'นามสกุล',
            'dep_name' => 'ตำแหน่ง',
            'group_work' => 'กลุ่มงาน',
            'email' => 'อีเมล์',
            'phone' => 'เบอร์โทรศัพท์',
            'line_id' => 'ID LINE',
            'photo' => 'ภาพประจำตัว',
            'sign_photo' => 'ลายเซนต์',
        ];
    }

    /**
     * Signs user up.
     *
     * @return bool whether the creating new account was successful and email was sent
     */
    public function signup()
    {
        if (!$this->validate()) {
            return null;
        }
        
        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateEmailVerificationToken();

        // return $user->save() && $this->sendEmail($user);
        return $user->save();
    }

    /**
     * Sends confirmation email to user
     * @param User $user user model to with email should be send
     * @return bool whether the email was sent
     */
    protected function sendEmail($user)
    {
        return Yii::$app
            ->mailer
            ->compose(
                ['html' => 'emailVerify-html', 'text' => 'emailVerify-text'],
                ['user' => $user]
            )
            ->setFrom([Yii::$app->params['supportEmail'] => Yii::$app->name . ' robot'])
            ->setTo($this->email)
            ->setSubject('Account registration at ' . Yii::$app->name)
            ->send();
    }
}