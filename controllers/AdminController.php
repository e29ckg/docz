<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\User;
use app\models\UserProfile;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\web\UploadedFile;
use yii\helpers\Url;
/**
 * Site controller
 */
class AdminController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['user','index','set_active','set_deactive','update_profile','reset_password'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['user','update_profile','reset_password','set_active','set_deactive'],
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
        return $this->render('index');
    }

    public function actionView($id = null)
    {
        if($id == null){
            $id = Yii::$app->user->id;
        }
        $model = User::findOne(['id' => $id]);
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('admin_user_view',[
                'model' => $model,
            ]);
        }
        return $this->render('admin_user_view', [
            'model' => $model,
        ]);
    }


    public function actionUser()
    {
        $models = User::find()->all();
        return $this->render('admin_user',[
            'models' =>$models
        ]);
    }

    

    /**
     * Displays contact page.
     *
     * @return mixed
     */
    public function actionSet_active($id)
    {
        $model = User::findOne($id);
        $model->status = 10;
        if ($model->save()) {           
            Yii::$app->session->setFlash('success', $model->username.' Active.');           
            return $this->redirect(['/admin/user']);
        } else {
            return $this->redirect(['/admin/user']);
        }
        
    }

    public function actionSet_deactive($id)
    {
        $model = User::findOne($id);
        $model->status = 9;
        if ($model->save()) {           
            Yii::$app->session->setFlash('danger', $model->username.' ระงับการใช้งาน.');           
            return $this->redirect(['/admin/user']);
        } 
        return $this->redirect(['/admin/user']);               
    }

    public function actionReset_password($id)
    {
        $model = User::findOne($id);
        $model->password_hash = Yii::$app->security->generatePasswordHash('password');
        if ($model->save()) {           
            Yii::$app->session->setFlash('success', 'Reset to -> password');           
            return $this->redirect(['/admin/user']);
        } 
        return $this->redirect(['/admin/user']); 
    }

    public function actionUpdate_profile($id = null)
    {
        // $this->layout = 'main-login';        
        $model = UserProfile::findOne(['user_id' => $id]);
        $photo_old = $model->photo;
        $sign_photo_old = $model->sign_photo;

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {      
            // $model->user_id = $uid;
            $model->pfname = $model->pfname;
            $model->name = $model->name;
            $model->sname = $model->sname;
            $model->dep_name = $model->dep_name;
            $model->group_work = $model->group_work;
            $model->phone = $model->phone;
            $model->line_id = $model->line_id;
            // $model->photo = $photo_old;
            // $model->sign_photo =$sign_photo_old;
            if($name = UploadedFile::getInstance($model, 'photo')){            
                $path = 'uploads/profile/'.md5($name->basename.rand(1,400)).'.'.$name->extension;
                if ($name->saveAs($path)) {                    // file is uploaded successfully
                    if(!$photo_old == '' && file_exists(Url::to('@webroot/'.$photo_old ))){
                        unlink(Url::to('@webroot/'.$photo_old ));
                    }
                    $model->photo = $path;
                }
            }else{
                $model->photo = $photo_old;
            }
            if($nameS = UploadedFile::getInstance($model, 'sign_photo')){            
                $pathS = 'uploads/profile/'.md5($nameS->basename.rand(1,400)).'.'.$nameS->extension;
                if ($nameS->saveAs($pathS)) {                    // file is uploaded successfully
                    if(!$sign_photo_old == '' && file_exists(Url::to('@webroot/'.$sign_photo_old ))){
                        unlink(Url::to('@webroot/'.$sign_photo_old ));
                    }
                    $model->sign_photo = $pathS;
                }
            }else{
                $model->sign_photo = $sign_photo_old;
            }
            if($model->save()){
                Yii::$app->session->setFlash('success', 'ปรับปรุงข้อมูลเรียบร้อย');
                return $this->redirect(['user']);
            }

        } 
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_admin_profile_update',[
                'model' => $model,
            ]);
        }    
        return $this->render('_admin_profile_update', [
            'model' => $model,
        ]);        
    }
    
}