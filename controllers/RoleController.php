<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\Role;
use app\models\RoleName;
use app\models\RolePower;
use app\models\User;
use app\models\UserProfile;
use yii\web\Response;
// use yii\widgets\ActiveForm;
use yii\helpers\Url;

use yii\bootstrap\ActiveForm;
/**
 * Site controller
 */
class RoleController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index','role_name_create','role_name_update','role_name_del','role_power_create','role_name_update','role_name_del'],
                'rules' => [
                    [
                        'actions' => [''],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['index','role_name_create','role_name_update','role_name_del','role_power_create','role_name_update','role_name_del'],
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
    
    public function actionIndex($id = null)
    {
        $models_role_name = RoleName::find()->where(['status' => '1'])->orderBy(['id'=>SORT_ASC])->all();
        $models_role_power = RolePower::find()->orderBy(['role_name_id'=>SORT_ASC])->all();
        return $this->render('index', [
            'models_role_name' => $models_role_name,
            'models_role_power' => $models_role_power,
        ]);
    }

    public function actionRole_name_create()
    {
        $model = new RoleName();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 

            if($model->save()){
                Yii::$app->session->setFlash('success', '????????????????????????????????????????????????????????????');
                return $this->redirect(['/role/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_name',[
                'model' => $model,
                'title' => '??????????????????????????????????????????'
            ]);
        }
        return $this->render('_role_name', [
            'model' => $model,
            'title' => '??????????????????????????????????????????'
        ]);            
    }

    public function actionRole_name_update($id)
    {
        $model = RoleName::findOne($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {     
            if ($model->save()) {           
                Yii::$app->session->setFlash('success', '?????????????????????????????????????????????????????????????????????');           
                return $this->redirect(['/role/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_name',[
                'model' => $model,
                'title' => '?????????????????????????????????'
            ]);
        } 
        return $this->render('_role_name',[
            'model' => $model,
            'title' => '?????????????????????????????????'
        ]);
    }

    public function actionRole_name_del($id)
    {        
        $model = RoleName::findOne($id);  
        $model->status = 0;    
        if($model->save()){
            Yii::$app->session->setFlash('success', '???????????????????????????????????????????????????');           
        }
        return $this->redirect(['/role/index']);      
    }


//------------------------------// Role Power //----------------------------------------------------------------
    public function actionRole_power_create()
    {
        $model = new RolePower();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 
            $m_role_count = Role::find()->where(['user_id'=>$model->user_id,'role_name_id'=>$model->role_name_id])->count();
            if($m_role_count == 0){
                $m_role = new Role();
                $m_role->user_id = $model->user_id;
                $m_role->role_name_id = $model->role_name_id;
                $m_role->name_dep1 = $model->role_name();
                $m_role->save();
            }
            if($model->save()){
                Yii::$app->session->setFlash('success', '????????????????????????????????????????????????????????????');
                return $this->redirect(['/role/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_power',[
                'model' => $model,
                'title' => '???????????????'
            ]);
        }
        return $this->render('_role_power', [
            'model' => $model,
            'title' => '???????????????'
        ]);            
    }

    public function actionRole_power_update($id)
    {
        $model = RolePower::findOne($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {     
            $m_role_count = Role::find()->where(['user_id'=>$model->user_id,'role_name_id'=>$model->role_name_id])->count();
            if($m_role_count == 0){
                $m_role = new Role();
                $m_role->user_id = $model->user_id;
                $m_role->role_name_id = $model->role_name_id;
                $m_role->name_dep1 = $model->role_name();
                $m_role->save();
            }
            if ($model->save()) {           
                Yii::$app->session->setFlash('success', '?????????????????????????????????????????????????????????????????????');           
                return $this->redirect(['/role/index']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_power',[
                'model' => $model,
                'title' => '?????????????????????????????????'
            ]);
        } 
        return $this->render('_role_power',[
            'model' => $model,
            'title' => '?????????????????????????????????'
        ]);
    }

    public function actionRole_power_del($id)
    {        
        $model = RolePower::findOne($id);  
        if($model->delete()){
            Yii::$app->session->setFlash('success', '???????????????????????????????????????????????????');           
        }
        return $this->redirect(['/role/index']);      
    }
    
}