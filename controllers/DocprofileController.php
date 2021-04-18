<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\RoleName;
use app\models\User;
use app\models\DocProfile;
use app\models\DocProfileSub;
use yii\web\Response;
// use yii\widgets\ActiveForm;
use yii\helpers\Url;

use yii\bootstrap\ActiveForm;
/**
 * Site controller
 */
class DocprofileController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index'],
                'rules' => [
                    [
                        'actions' => [''],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'doc_profile_del' => ['post'],
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
    
    public function actionIndex()
    {
        $models= DocProfile::find()->orderBy(['id'=>SORT_ASC])->all();
        return $this->render('index', [
            'models' => $models,
        ]);
    }

    public function actionDoc_profile_create()
    {
        $model = new DocProfile();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 

            if($model->save()){
                Yii::$app->session->setFlash('success', 'เพิ่มข้อมูลเรียบร้อย');
                return $this->redirect(['/docprofile/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_docprofile',[
                'model' => $model,
            ]);
        }
        return $this->render('_docprofile', [
            'model' => $model,
        ]);            
    }

    public function actionDoc_profile_update($id)
    {
        $model = DocProfile::findOne($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {     
            if ($model->save()) {           
                Yii::$app->session->setFlash('success', 'ปรับปรุงข้อมูลเรียบร้อย');           
                return $this->redirect(['/docprofile/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_docprofile',[
                'model' => $model,
            ]);
        } 
        return $this->render('_docprofile',[
            'model' => $model,
        ]);
    }

    public function actionDoc_profile_del($doc_profile_id)
    {      
        $modelD = DocProfile::findOne($doc_profile_id); 
        if($modelD->delete()){
            Yii::$app->session->setFlash('success', 'ลบข้อมูลเรียบร้อย');           
        } 
        $models = DocProfileSub::find()->where(['doc_profile_id' => $doc_profile_id])->all();  
        foreach( $models as $model){
            if($model->delete()){
                Yii::$app->session->setFlash('success', 'ลบข้อมูลเรียบร้อย');           
            }
        }
        
        return $this->redirect(['/docprofile/index']);    
    }


//------------------------------// Role Power //----------------------------------------------------------------
    public function actionDoc_profile_sub_create($doc_profile_id)
    {
        $model = new DocProfileSub();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 
            $model->sort = DocProfileSub::find()->where(['doc_profile_id'=>$doc_profile_id])->count() + 1;
            if($model->save()){
                Yii::$app->session->setFlash('success', 'เพิ่มข้อมูลเรียบร้อย');
                return $this->redirect(['/docprofile/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_doc_profile_sub',[
                'model' => $model,
                'doc_profile_id' => $doc_profile_id
            ]);
        }
        return $this->render('_doc_profile_sub', [
            'model' => $model,
            'doc_profile_id' => $doc_profile_id
        ]);            
    }

    

    public function actionDoc_profile_sub_del($doc_profile_id)
    {        
        $count = DocProfileSub::find()->where(['doc_profile_id'=>$doc_profile_id])->count();
        if($count > 0 ){
            $model = DocProfileSub::find()->where(['doc_profile_id' => $doc_profile_id,'sort'=>$count])->one();  
            // foreach( $models as $model){
                if($model->delete()){
                    Yii::$app->session->setFlash('success', 'ลบข้อมูลเรียบร้อย');           
                }
            // }
        }        
        return $this->redirect(['/docprofile/index']);      
    }
    
}