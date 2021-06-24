<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\User;
use app\models\UserProfile;
use app\models\Docz;
use app\models\DocCat;
use app\models\DocCatName;
// use app\models\DocFile;
// use app\models\DocManage;
// use app\models\DocProfile;
use app\models\DocUserRead;
// use app\models\Role;
// use yii\web\Response;
// use yii\widgets\ActiveForm;
// use yii\web\UploadedFile;
// use yii\helpers\Url;
/**
 * Site controller
 */
class ApiController extends Controller
{
    public $enableCsrfValidation = false; 

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['create'],
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
                    'profile' => ['post'],
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
    public function actionIndex()
    {
        $users = User::find()->where(['status'=>10])->all();
        $data = [];
        $data['status'] = true;
        foreach($users as $user){
            $doc_user_read_count = DocUserRead::doc_un_read_count_by_user($user->id);
            if($doc_user_read_count > 0){
                $data['user'][] = [
                    'doc_num' =>  $doc_user_read_count,   
                    'name' => $user->getname(),                    
                ];         
            }
                      
        }
        header('Content-type:application/json;charset=utf-8');
        rsort($data['user']);
        return json_encode($data);        
              
    }
    public function actionUser_unread($id)
    {       
        $doc_user_read_count = DocUserRead::doc_un_read_count_by_user($id);
         
        header('Content-type:application/json;charset=utf-8');
        return json_encode($doc_user_read_count);        
              
    }
    
    public function actionProfile($id = null)
    {
        $user = User::find()->where(['status'=>10,'id'=>$id])->one();
        if($user){
            $data = [
                'id' => $user->id,
                'name' => $user->getname()
            ];
            $data['status'] = true;  
        }else{
            $data['status'] = false;      

        }
        header('Content-type:application/json;charset=utf-8');        
        return json_encode($data);        
              
    }

}