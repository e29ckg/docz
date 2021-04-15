<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\User;

/**
 * Signup form
 */
class ProfileChangePassword extends Model
{    
    public $password;
    public $rpassword;
    public $id;

    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            
            [['password','rpassword'], 'required'],
            ['rpassword', 'compare', 'compareAttribute' => 'password'],
            ['password', 'string', 'min' => Yii::$app->params['user.passwordMinLength']],           

        ];
    }

    public function attributeLabels()
    {
        return [
            'password' => 'Password',
            'rpassword' => 'Retype Password',
        ];
    }

}