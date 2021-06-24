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
        $data = [];
        foreach($users as $user){
            $doc_user_read_count = DocUserRead::doc_un_read_count_by_user($user->id);
            if($doc_user_read_count > 10){
                $data[] = [
                    'doc_num' =>  $doc_user_read_count,   
                    'name' => $user->getname(),                    
                ];         
            }                      
        }
        rsort($data);

        $sms = 'ระบบจัดเก็บเอกสาร'."\n";
        $sms .= '🔥รายชื่อผู้ที่มีหนังสือที่ต้องรับทราบ(มากกว่า 10 เรื่อง)'."\n";
        $sms .= "\n";
        // $sms .= count($data);
        $i = 1;
        foreach($data as $da){
            $sms .= '😡 '.$da['name'];
            $sms .= "\n";
            $i++;
        }                      
        $sms .= "\n";
        $sms .= 'ตรวจสอบได้ที่ http://10.37.64.01/docz/';
        $token = 'zCaNve4bPNP6oE5SWgvRCjzR2mDfungliwdLZlEZaAF';      
        Line::Send($token,$sms);
        
        return $sms;
    }

    
}