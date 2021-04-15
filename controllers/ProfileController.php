<?php

namespace app\controllers;

use Yii;
// use app\models\UserProfile;
use app\models\User;
use app\models\UserProfile;
use app\models\ProfileChangePassword;
use app\models\SignupFormProfile;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\bootstrap\ActiveForm;
// use app\controllers\Response;

/**
 * ProfileController implements the CRUD actions for UserProfile model.
 */
class ProfileController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'only' => ['index','create', 'update','view','Change_password'],
                'rules' => [
                    // deny all POST requests
                    [
                        'allow' => false,
                        'verbs' => ['POST']
                    ],
                    // allow authenticated users
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    // everything else is denied
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all UserProfile models.
     * @return mixed
     */
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => UserProfile::find(),
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single UserProfile model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id = null)
    {
        if($id == null){
            $id = Yii::$app->user->id;
        }
        $model = User::findOne(['id' => $id]);
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('view',[
                'model' => $model,
            ]);
        }
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new UserProfile model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new UserProfile();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {            
            $pr = UserProfile::findOne(['user_id' => Yii::$app->user->identity->id]);
                if($pr){
                    Yii::$app->session->set('profile',[
                        'user_id' => $pr->user_id,
                        'name' => $pr->name,   
                        'dep_name' => $pr->dep_name                     
                        ]);
                }else{
                    Yii::$app->session->setFlash('danger', ['ไม่มีโปรไฟล์']);
                    return $this->redirect(['profile/create']);
                }
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    public function actionCreate_profile()
    {
        $this->layout = 'main-login';
        $model = new SignupFormProfile();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {       
            
            $modelU = new User();
            $modelP = new UserProfile();

            $uid = time();
            $modelU->id = $uid;
            $modelU->username = $model->username;
            $modelU->password_hash = Yii::$app->security->generatePasswordHash($model->password);
            $modelU->email = $uid;

            $modelP->user_id = $uid;
            $modelP->pfname = $model->pfname;
            $modelP->name = $model->name;
            $modelP->sname = $model->sname;
            $modelP->dep_name = $model->dep_name;
            $modelP->group_work = $model->group_work;
            // $modelP->photo = 1;
            // $modelP->sign_photo = 1;
            // $modelU->save();
            if($modelP->save() && $modelU->save()){
                Yii::$app->session->setFlash('success', 'Thank you for registration. รอการติกต่อกลับ');
                return $this->redirect(['site/index']);
            }else{
                return $this->render('_profile_create', [
                    'model' => $model,
                ]); 
            }

        } else {
            return $this->render('_profile_create', [
                'model' => $model,
            ]);
        }
    }

    public function actionUpdate_profile($id = null)
    {
        // $this->layout = 'main-login';
        if($id == null){
            $id = Yii::$app->user->id;
        }
        $model = UserProfile::findOne(['user_id' =>$id]);

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
            // $modelP->photo = 1;
            // $modelP->sign_photo = 1;
            // $modelU->save();
            if($model->save()){
                Yii::$app->session->setFlash('success', 'ปรับปรุงข้อมูลเรียบร้อย');
                return $this->redirect(['/profile']);
            }else{
                return $this->render('_profile_update', [
                    'model' => $model,
                ]); 
            }

        } 
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_profile_update',[
                'model' => $model,
            ]);
        }    
        return $this->render('_profile_update', [
            'model' => $model,
        ]);
        
    }

    /**
     * Updates an existing UserProfile model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the UserProfile model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return UserProfile the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = UserProfile::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionChange_password($id = null)
    {
        // $this->layout = 'main';
        $model = new ProfileChangePassword();
        if($id == null){
            $id = Yii::$app->user->id;
        }
        $model->id = $id;
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {       
            $modelU = User::findOne($model->id);
            $modelU->password_hash = Yii::$app->security->generatePasswordHash($model->password);
            // $modelU->update();
            if($modelU->save()){
                Yii::$app->session->setFlash('success', 'ปรับปรุงเรียบร้อย..');
                return $this->redirect(['/profile']);
            }

        } 
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_change_password', [
                'model' => $model,
            ]);
        }
        return $this->render('_change_password', [
            'model' => $model,
        ]);
       
    }
    
}
