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
use app\models\DocUserRead;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\web\UploadedFile;
use yii\helpers\Url;
/**
 * Site controller
 */
class DoczController extends Controller
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
    public function actionIndex()
    {
        $models = Docz::find()->where(['st'=>1])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index',[
            'models' => $models
        ]);
    }

    public function actionIndex_2() //อยู่ระหว่างดำเนินการ
    {
        $models = Docz::find()->where(['st'=>2])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index_2',[
            'models' => $models
        ]);
    }

    public function actionIndex_3() //เสร็จสิ้นแล้ว
    {
        $models = Docz::find()->where(['st'=>3])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index_3',[
            'models' => $models
        ]);
    }

    public function actionView($id)
    {
        $model = Docz::findOne($id);

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('view',[
                'model' => $model,
            ]);
        } 

        return $this->render('view',[
            'model' => $model
        ]);
    }

    public function actionView_att($id)
    {
        $model = DocFile::findOne($id);

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('view_att',[
                'model' => $model,
            ]);
        } 

        return $this->render('view_att',[
            'model' => $model
        ]);
    }

    public function actionView_st($id)
    {
        $model = Docz::findOne($id);

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('view_st',[
                'model' => $model,
            ]);
        } 

        return $this->render('view_st',[
            'model' => $model
        ]);
    }
    
    public function actionCreate()
    {
        $model = new Docz();
        
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {      
            if($name = UploadedFile::getInstance($model, 'file')){            
                $path = 'uploads/docz/'.md5($name->basename.rand(1,400)).'.'.$name->extension;
                if ($name->saveAs($path)) { 
                    $model->file = $path;
                }
            }
            $model->doc_form = $this->code; //ชื่อโปรแกรม
            $model->r_date = date("Y-m-d h:i:s", strtotime($model->r_date)); //ชื่อโปรแกรม
            $model->doc_date = date("Y-m-d", strtotime($model->doc_date)); //ชื่อโปรแกรม
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย'.$model->doc_date);
                return $this->redirect(['index']);
            }

        } 
        
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_create',[
                'model' => $model,
            ]);
        }    
        return $this->render('_create', [
            'model' => $model,
        ]); 
    }

   

    public function actionUpdate($id)
    {
        $model = Docz::findOne($id);
        $file = $model->file;

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {    
            if($name = UploadedFile::getInstance($model, 'file')){            
                $path = 'uploads/docz/'.md5($name->basename.rand(1,400)).'.'.$name->extension;
                if ($name->saveAs($path)) {                    // file is uploaded successfully
                    if(!$file == '' && file_exists(Url::to('@webroot/'.$file ))){
                        unlink(Url::to('@webroot/'.$file ));
                    }
                    $model->file = $path;
                }
            }else{
                $model->file = $file;
            }
            
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย'.$model->doc_date);
                return $this->redirect(['index']);
            }

        } 
        
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_create',[
                'model' => $model,
            ]);
        }    
        return $this->render('_create', [
            'model' => $model,
        ]); 
    }

    public function actionDel($id)
    {
        $model = Docz::findOne($id);
        foreach($model->doc_manage as $dm){
            $dm->delete();
        }
        foreach($model->doc_file as $df){
            $this->del_file($df->file);
            $df->delete();
        }

        $path = $model->file;
        if ($model->delete()) {                    // file is uploaded successfully
            if(!$path == '' && file_exists(Url::to('@webroot/'.$path ))){
                unlink(Url::to('@webroot/'.$path));
            }
        }
        

        return $this->redirect(['index']);
    }

    public function actionDel_att($id)
    {
        $model = DocFile::findOne($id);
        $path = $model->file;
        if ($model->delete()) {                    // file is uploaded successfully
            if(!$path == '' && file_exists(Url::to('@webroot/'.$path ))){
                unlink(Url::to('@webroot/'.$path));
            }
        }
        return $this->redirect(['index']);
    }

    public function actionAtt($id)
    {
        $model = new DocFile();
        
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {    
            if($name = UploadedFile::getInstance($model, 'file')){            
                $path = 'uploads/docz/'.md5($name->basename.rand(1,400)).'.'.$name->extension;
                if ($name->saveAs($path)) {                   // file is uploaded successfully
                    
                    $model->file = $path;
                    $model->docz_id = $id;
                    $model->doc_form = 'DocZ';
                    $model->ext = $name->extension;
                    $model->user_id_create = Yii::$app->user->id;
                }

                if($model->save()){
                    Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
                    return $this->redirect(['index']);
                }
            }
        } 

        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_add_file_att',[
                'model' => $model,
            ]);
        }    
        return $this->render('_add_file_att', [
            'model' => $model,
        ]);
    }


    //---------------------กด เสนอ---------------------
    public function actionSend($id)
    {
        $model = Docz::findOne($id);
        $modelD = DocProfile::find()->where(['code' => $this->code])->one();        
        foreach($modelD->docps as $ds){   
            $count = DocManage::find()->where(['doc_id'=>$id,'role_name_id'=>$ds->role_name_id])->count();         
            if($count == 0){
                $m = new DocManage();
                $m->doc_form ='Docz';
                $m->doc_id = $id; 
                $m->user_id = Yii::$app->user->id;
                $m->role_name_id = $ds->role_name_id;
                $m->sort = $ds->sort;
                $m->save();

            }
            // $m->date_begin = '';
            // $m->date_end ='';
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_send',[
                'model' => $model,
                // 'modelD' => $modelD,
            ]);
        }    
        return $this->render('_send', [
            'model' => $model,
            // 'modelD' => $modelD,
        ]); 
    }
    public function actionSend_del($id)
    {
        $model = DocManage::find()->where(['id'=>$id])->One();    
        $id = $model->doc_id;   
        // $model->delete();
        if(Yii::$app->request->isAjax){
            // return $this->renderAjax('_send',[
            //     'model' => $model,
            //     // 'modelD' => $modelD,
            // ]);
            return $this->redirect(['send','id'=>$id]);
        }    
        return $this->redirect(['send','id'=>$id]);
        // return $this->render('_send', [
        //     'model' => $model,
        //     // 'modelD' => $modelD,
        // ]); 
    }


    public function del_file($file){
        $path = $file;                    // file is uploaded successfully
        if(!$path == '' && file_exists(Url::to('@webroot/'.$path ))){
            unlink(Url::to('@webroot/'.$path));
            return true;
        }
        return false;
    } 
    
    public function actionStart($id){
        $model = Docz::findOne($id);
        $model->user_create = Yii::$app->user->id;
        $model->st = 2;
        $model->start = date("Y-m-d H:i:s");
        $model->save(); 
        
        foreach($model->doc_manage as $dm){
            if($dm->sort == 1){
                $dm->st = 2;
            }else{
                $dm->st = 1;
            }                
            $dm->created = date("Y-m-d H:i:s");
            $dm->save();
        }
        $model->save();
        return $this->redirect(['index']);;
    }

    public function actionSend_to_user($id){
        $Docz = Docz::findOne($id);
        $MUser = User::find()->where(['status'=>10])->all(); 
        
        $model = new DocUserRead();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {   
            foreach($model->user_id as $ms){
                $data[] = $ms;
                $DUR = DocUserRead::find()->where(['doc_id'=>$Docz->id,'user_id'=>$ms])->count();
                if($DUR == 0){
                    $model = new DocUserRead();
                    $model->user_id = $ms;
                    $model->doc_id = $Docz->id;
                    $model->ckeck = 0;
                    $model->created = date("Y-m-d H:i:s");
                    $model->save();
                }
            }
            return print_r($data);
        }
        return $this->render('_send_to_user',[
            'MUser' => $MUser,
            'model' => $model
        ]);
    } 
    public function actionIndex_to_read(){
        $models = DocUserRead::find()->where(['ckeck'=>0,'user_id'=>Yii::$app->user->id])->all();             
        
        return $this->render('index_to_read',[
            'models' => $models
        ]);
    } 
    public function actionTo_read($id){
        $model = Docz::findOne($id);             
        $modelDs = DocUserRead::find()->where(['doc_id'=>$model->id,'user_id'=>Yii::$app->user->id])->all();
        foreach($modelDs as $Ds){
            if($Ds->ckeck == 0){
                $Ds->ckeck = 1;
                $Ds->ip = Yii::$app->getRequest()->getUserIP();
                $Ds->updated = date("Y-m-d H:i:s");
                $Ds->save();
            }
        }
        return $this->render('_to_read',[
            'model' => $model
        ]);
    } 

    public function actionAll(){
        $models = Docz::find()
                    ->orderBy(['id'=>SORT_DESC])
                    ->limit(1000)
                    ->all();             
        
        return $this->render('index_all',[
            'models' => $models
        ]);
    }

    public function actionAll_to_read($id){
        $model = Docz::findOne($id);
        return $this->render('_all_to_read',[
            'model' => $model
        ]);
    } 

}