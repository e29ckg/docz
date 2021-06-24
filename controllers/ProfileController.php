<?php

namespace app\controllers;

use Yii;
// use app\models\UserProfile;
use app\models\User;
use app\models\UserProfile;
use app\models\Role;
use app\models\ProfileChangePassword;
use app\models\SignupFormProfile;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\bootstrap\ActiveForm;
// use app\models\UploadForm;
use yii\web\UploadedFile;
// use app\controllers\Response;
use yii\helpers\Url;

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
     * Displays a single UserProfile model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id = null)
    {
        $client_id = 'eSAUJzHEddGlHDi6wK7CO6';
        $api_url = 'https://notify-bot.line.me/oauth/authorize?';
        $callback_url = Yii::$app->params['line_callback'];

        $query = [
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $callback_url,
            'scope' => 'notify',
            'state' => 'mylinenotify'
        ];
        
        $result = $api_url . http_build_query($query);

        if($id == null){
            $id = Yii::$app->user->id;
        }
        $model = User::findOne(['id' => $id]);
        
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('view',[
                'result' => $result,
                'model' => $model,
            ]);
        }
        return $this->render('view', [
            'result' => $result,
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

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

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
            $modelU->status = 10;

            $modelP->user_id = $uid;
            $modelP->pfname = $model->pfname;
            $modelP->name = $model->name;
            $modelP->sname = $model->sname;
            $modelP->dep_name = $model->dep_name;
            $modelP->group_work = $model->group_work;
            // $modelP->photo = 1;
            // $modelP->sign_photo = 1;
            // $modelU->save();
            if($name = UploadedFile::getInstance($model, 'photo')){            
                $path = 'uploads/profile/'.md5($name->basename.rand(1,400)).'.'.$name->extension;
                if ($name->saveAs($path)) {                   // file is uploaded successfully
                    $modelP->photo = $path;
                }
            }
            if($nameS = UploadedFile::getInstance($model, 'sign_photo')){            
                $pathS = 'uploads/profile/'.md5($nameS->basename.rand(1,400)).'.'.$nameS->extension;
                if ($nameS->saveAs($pathS)) {                   // file is uploaded successfully
                    $modelP->sign_photo = $pathS;
                }
            }

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
                return $this->redirect(['/profile/view','id'=>$model->user_id]);
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
                return $this->redirect(['/profile/view']);
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

//------------------------------Role------------------------------------

    public function actionRole_create($id)
    {
        $model = new Role();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 

            if($model->save()){
                Yii::$app->session->setFlash('success', 'เพิ่มข้อมูลเรียบร้อย');
                return $this->redirect(['/profile/view','id'=>$id]);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_form',[
                'model' => $model,
                'id' => $id,
                'title' => 'เพิ่มตำแหน่ง'
            ]);
        }
        return $this->render('_role_form', [
            'model' => $model,
            'id' => $id,
            'title' => 'เพิ่มตำแหน่ง'
        ]);            
    }

    public function actionRole_update($id)
    {
        $model = Role::findOne($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) { 

            if($model->save()){
                Yii::$app->session->setFlash('success', 'ปรับปรุงข้อมูลเรียบร้อย');
                return $this->redirect(['/profile/view']);
            }
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_role_form',[
                'model' => $model,
                'id' => $model->user_id,
                'title' => 'แก้ไขตำแหน่ง'
            ]);
        }
        return $this->render('_role_form', [
            'model' => $model,
            'id' => $model->user_id,
            'title' => 'แก้ไขตำแหน่ง'
        ]);            
    }

    public function actionRole_del($id){
        $model = Role::findOne($id);
        if($model->delete()){
            Yii::$app->session->setFlash('success', 'ลบข้อมูลเรียบร้อย');
        }
        return $this->redirect(['/profile/view']);
    }

    public function actionCallback()
    {
        
        $client_id = 'eSAUJzHEddGlHDi6wK7CO6';
        $client_secret = 'Bs0IyQPhd9S7uhZPlv0QUarnQrZasLdKbgwaYcXSvNr';

        $api_url = 'https://notify-bot.line.me/oauth/token';
        $callback_url = Yii::$app->params['line_callback'];

        parse_str($_SERVER['QUERY_STRING'], $queries);

        //var_dump($queries);
        $fields = [
            'grant_type' => 'authorization_code',
            'code' => $queries['code'],
            'redirect_uri' => $callback_url,
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ];
        
        try {
            $ch = curl_init();
        
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
            $res = curl_exec($ch);
            curl_close($ch);
        
            if ($res == false){
                // Yii::$app->session->setFlash('info', 'ส่งไม่ได้');
                return false;
                throw new Exception(curl_error($ch), curl_errno($ch));
            }
        
            $json = json_decode($res);

            if($json->status == 200){
                $model = UserProfile::find()->where(['user_id'=> Yii::$app->user->id])->one();
                $model->line_id =  $json->access_token;
                $model->save();
                Yii::$app->session->setFlash('success', 'บันทึกข้อมูล สำเร็จ');
                return $this->redirect(['/profile/view']);
            }
            
        
            //var_dump($json);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
            //var_dump($e);
        }
        // return $this->render('callback', [
        //     'json' => $json
        // ]);
        return $json = '';
    }
    
}
