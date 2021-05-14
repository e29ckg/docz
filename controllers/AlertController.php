<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\User;
use app\models\Line;
use app\models\DocUserRead;
class AlertController extends Controller
{   
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['create','index','index_2','index_3','index_4','view','check'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['index','create','index_2','index_3','index_4','view','check'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    // 'reset_password' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    // public function actionIndex()
    // {
        
    // }
    public function actionCheck_user_read()
    {
        $users = User::find()->where(['status'=>10])->all();
        $sms = 'ระบบจัดเก็บเอกสาร'."\n";
        foreach($users as $user){
            $doc_user_read_count = DocUserRead::doc_un_read_count_by_user($user->id);
            if($doc_user_read_count > 0){
                $sms .= $user->getname();
                $sms .= "\n";
                $sms .= 'ค้างอ่าน : '.$doc_user_read_count.' เรื่อง';
                $sms .= "\n"; 
                $sms .= "----------------";  
                $sms .= "\n";  
            }else{
                $sms .= 'ไม่มีค้างอ่าน';
                $sms .= "----------------";  
                $sms .= "\n";
            }
                      
        }
        $sms .= 'ตรวจสอบได้ที่ http://10.37.64.01/docz/';
        $token = '';        
        Line::Send($token,$sms);
        echo '<script type="text/javascript">
        window.close();
        </script>';
        return $sms;
    }

    
}