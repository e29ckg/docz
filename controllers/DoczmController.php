<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\User;
use app\models\Docz;
use app\models\DocFile;
use app\models\DocManage;
use app\models\DocProfile;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\web\UploadedFile;
use yii\helpers\Url;
/**
 * Site controller
 */
class DoczmController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public $code = 'DocZ';        //ชื่อโปรแกรม

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['create','index'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['index','create'],
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
    //     $models = Docz::find()->where(['st'=>2])->orderBy(['id'=>SORT_DESC])->all();
    //     return $this->render('index',[
    //         'models' => $models
    //     ]);
    // }

    public function actionIndex($role_name_id)
    {
        $models = DocManage::find()->where(['st'=>2,'role_name_id'=>$role_name_id])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index',[
            'models' => $models
        ]);
    }

    public function actionMg($id) //ดำเนินการเรื่องเดียว
    {
        $model = Docz::findOne($id);
        
        return $this->render('_mg',[
            'model' => $model
        ]);
    }

    public function actionMg_edit($id) //หน้า _mg กด 
    {
        $model = DocManage::findOne($id);
 
        if ($model->load(Yii::$app->request->post())) {      
            
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
                return $this->redirect(['mg','id'=>$model->doc_id]);
            }

        }

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_mg_edit',[
                'model' => $model,
            ]);
        } 
        return $this->render('_mg_edit',[
            'model' => $model
        ]);
    }

    public function actionSend($id) //หน้า _mg กด 
    {
        $DM = DocManage::findOne($id);
        $DM->user_id = Yii::$app->user->id;
        $DM->st = 3;
        $DM->updated = date("Y-m-d H:i:s");
        $sort_next = $DM->sort + 1;
        $DM->save();

        $DM_next = DocManage::find()->where(['doc_id' => $DM->doc_id,'sort' => $sort_next ])->one();
        $DO = Docz::findOne($DM->doc_id);
        // $count = count($DM_next);
        if(!empty($DM_next->id)){
            $DM_next->st = 2;
            $DO->st = 2;
            $DM_next->save();
        }else{            
            $DO->st = 3;
            $DO->end = date("Y-m-d H:i:s");
        }
        if($DO->save()){
            Yii::$app->session->setFlash('success', 'ส่งต่อแล้วจ้า');
            return $this->redirect(['index','role_name_id'=>$DM->role_name_id]);
        }           
        
        return $this->render('_mg',[
            'model' => $model
        ]);
    }

    


}