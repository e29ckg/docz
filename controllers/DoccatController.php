<?php

namespace app\controllers;

use Yii;
use app\models\Docz;
use app\models\UserProfile;
use app\models\DocCat;
use app\models\DocCatName;
use app\models\DocUserRead;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\web\UploadedFile;
use yii\helpers\Url;
/**
 * DoccatController implements the CRUD actions for UserDepName model.
 */
class DoccatController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all UserDepName models.
     * @return mixed
     */
    public function actionIndex($doc_cat_name_id = null)
    {
        if($doc_cat_name_id <> null){
            $models = DocCat::find()->where(['doc_cat_name_id'=>$doc_cat_name_id])->orderBy(['id'=>SORT_DESC])->all();
            $mDC = DocCatName::findOne($doc_cat_name_id);
            return $this->render('index_doc_cat_name', [
                'models' => $models,
                'tiile' => $mDC->name
            ]);
        }

        $models = DocCatName::find()->orderBy(['name'=>SORT_ASC])->all();
        return $this->render('index', [
            'models' => $models
        ]);

        
    }

    public function actionIndex_out()
    {
       
        $models = Docz::find()->where(['st'=>4])->orderBy(['id'=>SORT_DESC])->all();
        $count = 0;
        $data = [];
        foreach($models as $model){
            $doc_count = DocCat::find()->select('id')->where(['doc_id'=>$model->id])->count();
            if($doc_count == 0){
                $data[] = [
                    'doc_id' => $model->id,
                    'name' => $model->name_doc(),
                    'file' => $model->file
                ];
                $count++;
            }
        }
        return $this->render('index_out', [
            'data' => $data,
            'count' => $count
        ]);
    }

    public function actionIndex_doc_cat_name($doc_cat_name_id)
    {
        $models = DocCat::find()->where(['doc_cat_name_id'=>$doc_cat_name_id])->all();
        $mDC = DocCatName::findOne($doc_cat_name_id);
        return $this->render('index_doc_cat_name', [
            'models' => $models,
            'tiile' => $mDC->name
        ]);
    }

    /**
     * Displays a single UserDepName model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = Docz::findOne($id);
        // if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
        //     Yii::$app->response->format = Response::FORMAT_JSON;
        //     return ActiveForm::validate($model);
        // }
           
            
            if(isset($_POST['select'])){
                $DC = DocCat::find()->where(['doc_id'=>$model->id])->count();
                if($DC > 0){
                    $DC_name = DocCat::find()->where(['doc_id'=>$model->id])->all();
                    foreach($DC_name as $DC_N){
                        $DC_N->delete();
                    }
                }  
                foreach( $_POST['select'] as $slct){                
                    $model = new DocCat();
                    $model->doc_id = $id;
                    $model->doc_cat_name_id = (int)$slct;
                    $model->save();    
                }  
            }      
        

        $MUser = [];
        $MUPs_count = UserProfile::find()->count(); 
        if($MUPs_count > 0){
            $MUPs = UserProfile::find()->orderBy(['sort'=>SORT_ASC])->all();
            foreach($MUPs as $MUP){
                if($MUP->status()== 10){
                    $doc_user_read_count = DocUserRead::find()->where(['user_id'=>$MUP->user_id,'doc_id'=>$model->id])->count();
                    $doc_user_read_count > 0 ? $checked = 'checked' : $checked = '';
                    $MUser[] = [
                        'id' => $MUP->user_id,
                        'name' => $MUP->getname(),
                        'checked' => $checked
                    ];
                }
            }   
        }
        $DocCats = DocCatName::find()->all();  
        $DCats = [];
        foreach($DocCats as $docCat){
            $DocCat_count = DocCat::find()->where(['doc_id'=>$model->id,'doc_cat_name_id'=>$docCat->id])->count();
            $DocCat_count > 0 ? $selected = 'selected="selected"' : $selected = '';
            $DCats[] = [
                'id' => $docCat->id,
                'name' => $docCat->name,
                'selected' => $selected           
            ];                
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_view',[
                'model' => $model,
                'MUser' => $MUser,
                'DocCats'=>$DCats
            ]);
        } 
        return $this->render('_view',[
                'model' => $model,
                'MUser' => $MUser,
                'DocCats'=>$DCats
            ]);
    }

    /**
     * Creates a new UserDepName model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new DocCatName();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) {  
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
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

    /**
     * Updates an existing UserDepName model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        if ($model->load(Yii::$app->request->post())) {  
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
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

    /**
     * Deletes an existing UserDepName model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDel($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the UserDepName model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return UserDepName the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = DoccatName::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionDoc_create() //อยู่ระหว่างดำเนินการ
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
            $model->doc_form = 'cletter'; //ชื่อโปรแกรม
            // $model->r_number = $model->r_number.'/'.date("Y",strtotime(date("Y")+543));
            if($model->r_date == ''){
                $model->r_date = date("Y-m-d h:i:s"); //
            } 
            $model->r_date = date("Y-m-d h:i:s", strtotime($model->r_date));
            $model->doc_date = date("Y-m-d", strtotime($model->doc_date)); //ชื่อโปรแกรม
            $model->st = 4;
            if($model->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย'.$model->doc_date);
                return $this->redirect(['index_out']);
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

    public function actionDoc_update($id)
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
                return $this->redirect(['index_out']);
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

    // จ่ายหนังสือและจัดเก็บ
    public function actionSend_to_user($id){
        $Docz = Docz::findOne($id);
        // $MUser = User::find()->where(['status'=>10])->all(); 
        $MUser = [];
        $MUPs_count = UserProfile::find()->count(); 
        if($MUPs_count > 0){
            $MUPs = UserProfile::find()->orderBy(['sort'=>SORT_ASC])->all();
            foreach($MUPs as $MUP){
                if($MUP->status()== 10){
                    $MUser[] = [
                        'id' => $MUP->user_id,
                        'name' => $MUP->getname()
                    ];
                }
            }   
        }
          
        $model = new DocUserRead();

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post()) || isset($_POST['select'])) {   
            if($model->load(Yii::$app->request->post())){
                foreach($model->user_id as $ms){
                    $DUR = DocUserRead::find()->where(['doc_id'=>$Docz->id,'user_id'=>$ms])->count();
                    if($DUR == 0){
                        $model = new DocUserRead();
                        $model->user_id = $ms;
                        $model->doc_id = $Docz->id;
                        $model->check = 0;
                        $model->created = date("Y-m-d H:i:s");
                        $model->save();
    
                        $MUP = UserProfile::find()->select('line_id')->where(['user_id' => $model->user_id])->one();
                        $token = $MUP->line_id;
                        $sms = 'มีหนังสือ เรื่อง '.$Docz->name.' อ่านรายละเอียดได้ที่ http://10.37.64.01/docz/';
                        if( $token <> ''){
                            Docz::Line_send($token,$sms);
                        }
                    }else{
                        $DURS = DocUserRead::find()->where(['doc_id'=>$Docz->id,'user_id'=>$ms])->one();
                        $DURS->check = 0;
                        $DURS->save();
                    }
                } 
            }
            if(isset($_POST['chkAll'])){
                $sms = '<all>มีหนังสือเข้า เรื่อง '.$Docz->name.' อ่านรายละเอียดได้ที่ http://10.37.64.01/docz/';
                $token = '72bPVwppZfiMjDoiH6V5i6lygBiD1zPtPDOezUrk7L5';//line กลุ่ม
                Docz::Line_send($token,$sms);
            }
            if(isset($_POST['select'])){
                $DC = DocCat::find()->where(['doc_id'=>$Docz->id])->count();
                if($DC > 0){
                    $DC_name = DocCat::find()->where(['doc_id'=>$Docz->id])->all();
                    foreach($DC_name as $DC_N){
                        $DC_N->delete();
                    }
                }  
                foreach( $_POST['select'] as $slct){                
                    $model = new DocCat();
                    $model->doc_id = $Docz->id;
                    $model->doc_cat_name_id = (int)$slct;
                    $model->save();    
                }  
            } 
            $Docz->st = 4;
            $Docz->save();     
            return $this->redirect(['index_out']);
        }        
         
        return $this->render('_send_to_user',[
            'MUser' => $MUser,
            'model' => $model,
            'Docz' => $Docz,
        ]);
    } 

    public function actionIndex_to_read(){
        $models = DocUserRead::find()->where(['check'=>0,'user_id'=>Yii::$app->user->id])->all();             
        
        return $this->render('index_to_read',[
            'models' => $models
        ]);
    } 
}
