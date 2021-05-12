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
class AdmindocallController extends Controller
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
    public function actionIndex()
    {
        $models = Docz::find()->where(['st'=>4])->orderBy(['id'=>SORT_DESC])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index',[
            'models' => $models
        ]);
    }

    public function actionSend_to_user_by_admin($id){
        $Docz = Docz::findOne($id);        
        if(isset($_POST['select'])){
            DocCat::deleteAll(['doc_id' => $id]);
            foreach( $_POST['select'] as $slct){  
                $model = new DocCat();
                $model->doc_id = $id;
                $model->doc_cat_name_id = (int)$slct;
                $model->save();  
            }
            $a = $_POST['select'];
        }else{
            DocCat::deleteAll(['doc_id' => $id]);
        } 
        $MUser = [];
        $MUPs_count = UserProfile::find()->count(); 
        if($MUPs_count > 0){
            $MUPs = UserProfile::find()->orderBy(['sort'=>SORT_ASC])->all();            
            foreach($MUPs as $MUP){
                $doc_user_read_count = DocUserRead::find()->where(['doc_id'=>$id,'user_id'=> $MUP->user_id])->count();
                $doc_user_read_count > 0 ? $checked = 'checked="checked"' : $checked = '';
                if($MUP->status()== 10){
                    $MUser[] = [
                        'id' => $MUP->user_id,
                        'name' => $MUP->getname(),
                        'checked' => $checked
                    ];
                }
            }   
        }  
        $select_list = [];
        $doc_cat_names = DocCatName::find()->all();
            foreach($doc_cat_names as $doc_cat_name){
                $doc_cat_count = DocCat::find()->where(['doc_id'=>$id,'doc_cat_name_id'=>$doc_cat_name->id])->count();    
                $doc_cat_count > 0 ? $selected = 'selected=selected' : $selected = '';
                $select_list[] = [
                    'id' => $doc_cat_name->id,
                    'name' => $doc_cat_name->name,
                    'selected' => $selected 
                ];
            } 
        
        return $this->render('_send_to_user_by_admin',[
            'MUser' => $MUser,
            'Docz' => $Docz,
            'select_list'=>$select_list
        ]);
    }

    public function actionSend_to_user_by_admin_all($doc_id){
        $modelUs = User::find()->where(['status'=>10])->all();
        foreach($modelUs as $modelU){
            $doc_user_read_count = DocUserRead::find()->where(['doc_id'=>$doc_id,'user_id'=>$modelU->id])->count();
            if($doc_user_read_count > 0){
                $dur = DocUserRead::find()->where(['doc_id'=>$doc_id,'user_id'=>$modelU->id])->one();
                $dur->check = 0; 
            }else{
                $dur = new DocUserRead();
                $dur->doc_id = $doc_id;
                $dur->user_id = $modelU->id;
                $dur->created = date("Y-m-d h:i:s");
                $dur->save();
            }
            if($modelU->profile->line_id){
                $sms = '(All) เอกสารรอการอ่านอยู่นะค่ะ http://10.37.64.01/docz/';
                $token = $modelU->profile->line_id;
                Docz::Line_send($token,$sms);
            }
        }        
        return $this->redirect(['send_to_user_by_admin','id'=>$doc_id]);
    }

    public function actionSend_to_user_by_admin_singer($doc_id,$user_id){
        $doc_user_read_count = DocUserRead::find()->where(['doc_id'=>$doc_id,'user_id'=>$user_id])->count();
        if($doc_user_read_count > 0){
            $dur = DocUserRead::find()->where(['doc_id'=>$doc_id,'user_id'=>$user_id])->one();
            $dur->check = 0; 
        }else{
            $dur = new DocUserRead();
            $dur->doc_id = $doc_id;
            $dur->user_id = $user_id;
            $dur->created = date("Y-m-d h:i:s");
            $dur->save();
        }
        $modelUP = UserProfile::find()->select('line_id')->where(['user_id'=>$user_id])->one();
        if($modelUP->line_id){
            $sms = '('.$modelUP->getname().') หนังสือเข้าใหม่';
            $token = $modelUP->line_id;
            Docz::Line_send($token,$sms);
        }
        return $this->redirect(['send_to_user_by_admin','id'=>$doc_id]);
    }

    public function actionSend_to_user_by_admin_del_singer($doc_id,$user_id){
        DocUserRead::deleteAll(['doc_id'=>$doc_id,'user_id'=>$user_id]);
        return $this->redirect(['send_to_user_by_admin','id'=>$doc_id]);
    }

}