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
use app\models\Role;
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
                'only' => ['create','index','index_2','index_3','index_4','view'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['index','create','index_2','index_3','index_4','view'],
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

    public function actionIndex_4() //จ่ายแล้ว
    {
        $models = Docz::find()->where(['st'=>4])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index_4',[
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
            // $model->r_number = $model->r_number.'/'.date("Y",strtotime(date("Y")+543));
            if($model->r_date){
                $model->r_date = date("Y-m-d h:i:s"); //
            } 
            $model->r_date = date("Y-m-d h:i:s", strtotime($model->r_date));
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
        // $count = DocManage::find()->where(['doc_id'=>$id])->count();  
        if(empty($model->start)) {
            $this->stamp_rub($model->id);   //stamp เลขรับ ลงphp
        }  
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
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_send',[
                'model' => $model,
            ]);
        }    
        return $this->render('_send', [
            'model' => $model,
        ]); 
    }
    public function actionSend_del($id)
    {
        $model = DocManage::find()->where(['id'=>$id])->One();    
        $id = $model->doc_id;   
        if(Yii::$app->request->isAjax){
            return $this->redirect(['send','id'=>$id]);
        }    
        return $this->redirect(['send','id'=>$id]);
    }


    public function del_file($file){
        $path = $file;                    // file is uploaded successfully
        if(!$path == '' && file_exists(Url::to('@webroot/'.$path ))){
            unlink(Url::to('@webroot/'.$path));
            return true;
        }
        return false;
    } 
    //--------------กด เริ่ม------------
    public function actionStart($id){
        $model = Docz::findOne($id);
        $model->user_create = Yii::$app->user->id;
        $model->st = 2;
        $model->start = date("Y-m-d H:i:s");
        $model->save(); 
        
        foreach($model->doc_manage as $dm){
            if($dm->sort == 1){
                $dm->st = 2;
                foreach($dm->role_power as $RP){
                    if($RP->user_profile->line_id){
                        $sms = '(ห.อำนวยการ)มีหนังสือต้องเซ็น';
                        Docz::Line_send($RP->user_profile->line_id,$sms);
                    }
                }
            }else{
                $dm->st = 1;
            }                
            $dm->created = date("Y-m-d H:i:s");
            $dm->save();
            
        }
        $model->save();
        return $this->redirect(['index']);
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
                    $model->check = 0;
                    $model->created = date("Y-m-d H:i:s");
                    $model->save();
                }
            }
            if($Docz->st <> 4){
                $this->stamp_end($Docz->id);
            }
            $Docz->st = 4;
            $Docz->save();
            return $this->redirect(['index_3']);
        }
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_send_to_user',[
                'MUser' => $MUser,
                'model' => $model,
            ]);
        }
        return $this->render('_send_to_user',[
            'MUser' => $MUser,
            'model' => $model
        ]);
    } 

    public function actionIndex_to_read(){
        $models = DocUserRead::find()->where(['check'=>0,'user_id'=>Yii::$app->user->id])->all();             
        
        return $this->render('index_to_read',[
            'models' => $models
        ]);
    } 

    public function actionTo_read($id){
        $model = Docz::findOne($id);             
        $modelDs = DocUserRead::find()->where(['doc_id'=>$model->id,'user_id'=>Yii::$app->user->id])->all();
        foreach($modelDs as $Ds){
            if($Ds->check == 0){
                $Ds->check = 1;
                $Ds->ip = Yii::$app->getRequest()->getUserIP();
                $Ds->updated = date("Y-m-d H:i:s");
                $Ds->save();
            }
        } 
        return $this->render('_all_to_read',[
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
        // $this->layout = 'main-login';
        $model = Docz::findOne($id);
        return $this->render('_all_to_read',[
            'model' => $model
        ]);
    } 

    public function actionCheck_read($id){        
        $model = Docz::findOne($id);
        if(Yii::$app->request->isAjax){
            return $this->renderAjax('_check_read',[
                'model' => $model
            ]);
        }
        return $this->render('_check_read',[
            'model' => $model
        ]);
    } 

    public function stamp_rub($id){
        $model = Docz::findOne($id);
        $mpdf = new \Mpdf\Mpdf();
        // $mpdf->SetImportUse(); // only with mPDF <8.0

        $completePath = Url::to('@webroot/'.$model->file);
        $pagecount = $mpdf->SetSourceFile($completePath);
        $mpdf->SetDocTemplate($completePath);
        // $mpdf->AddFont('THSarabun', '', 'THSarabun.php'); //ธรรมดา
        $mpdf->SetFont('garuda', '', 8);
        $mpdf->AddPage();
            $mpdf->SetXY(140, 5);
            $mpdf->SetDrawColor(0, 0, 255);
            $mpdf->setTextColor('0', '0', '255');
            $mpdf->Cell(60, 6, 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์', 'LTR', 1, '');
            $mpdf->SetXY(140, 10);
            $mpdf->SetFont('garuda', '', 10);
            $mpdf->Cell(60, 6, 'รับที่  '.$model->r_number, 'LR', 1, '');
            $mpdf->SetXY(140, 15);
            $mpdf->Cell(60, 6, 'วันที่ '.$model->dateThaiTime($model->r_date), 'BLR', 1, '');
            // $mpdf->SetXY(140, 20);
            // $mpdf->Cell(60, 6, '', 'BLR', 1, '');
        
        for ($x = 2; $x <= $pagecount; $x++) {            
            $mpdf->AddPage();

        }
        
          
        // The height of the template as it was printed is returned as $actualsize['h']
        // The width of the template as it was printed is returned as $actualsize['w']
        // $mpdf->WriteHTML('Hello World'.$pagecount);
        // $mpdf->WriteHTML('Hello World');
        // $mpdf->WriteHTML( '1qqaaaaaa', 2 );
        // $mpdf->WriteHTML(' '.Url::to('@webroot/'.$model->file).'a');
        // $mpdf->Output();
        $mpdf->Output(Url::to('@webroot/'.$model->file), \Mpdf\Output\Destination::FILE);
        return true;
    } 

    public function stamp_end($id){
        $model = Docz::findOne($id);
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
        // 'format' => [190, 236],
        // 'orientation' => 'L'
        ]);

        $html = '<p>hi world สวัสดี</p>';
        $stylesheet = file_get_contents(Url::to('@webroot/css/pdf.css')); // external css
        $mpdf->WriteHTML($stylesheet,1);
        // $mpdf->WriteHTML($html,2);
        // $mpdf->SetImportUse(); // only with mPDF <8.0
        // $mpdf->text_input_as_HTML = true;

        $completePath = Url::to('@webroot/'.$model->file);
        $pagecount = $mpdf->SetSourceFile($completePath);
        $mpdf->SetDocTemplate($completePath);
        // $mpdf->AddFont('THSarabun', '', 'THSarabun.php'); //ธรรมดา       
        
        for ($x = 1; $x <= $pagecount; $x++) {            
            $mpdf->AddPage();
        }
        $mpdf->SetFont('garuda', '', 8);
        $mpdf->AddPage();
            $mpdf->SetXY(140, 5);
            $mpdf->SetDrawColor(0, 0, 255);
            $mpdf->setTextColor('0', '0', '255');
            $mpdf->Cell(60, 6, 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์', 'LTR', 1, '');
            $mpdf->SetXY(140, 10);
            $mpdf->SetFont('garuda', '', 10);
            $mpdf->Cell(60, 6, 'รับที่  '.$model->r_number, 'LR', 1, '');
            $mpdf->SetXY(140, 15);
            $mpdf->Cell(60, 6, 'วันที่ '.$model->dateThaiTime($model->r_date), 'BLR', 1, '');
            //หัวหน้าส่วน
            $x = 20;
            $y = 25;
            foreach($model->doc_manage_asc as $md){

                // $mpdf->SetXY($x, $y);
                $mpdf->WriteHTML($stylesheet,1);
                $mpdf->WriteHTML('<p id="hh">'.$md->ty.'</p>',2);
                $mpdf->WriteHTML('<p id="detail">'.$md->detail.'</p>',2);
                $role_name = Role::find()->where(['user_id'=>$md->user_id,'role_name_id'=>$md->role_name_id])->one();
                // $mpdf->WriteHTML('<br>');
                if($role_name){
                    $dep_name = '<br>'.$role_name->name_dep1; 
                    $dep_name .= $role_name->name_dep2 ? '<br>'.$role_name->name_dep2 : '';
                    $dep_name .= $role_name->name_dep3 ? '<br>'.$role_name->name_dep3 : '';
                }else{
                    $dep_name = '';
                }                
                if($md->profile->sign_photo){
                    $sign_photo = '<img id="img" src="'.Url::to('@webroot/'.$md->profile->sign_photo).'" alt="sign_photo"><br>';
                }else{
                    $sign_photo ='<br>';
                }
                // $mpdf->WriteHTML($sign_photo,2);
                $mpdf->WriteHTML('<p>'.$sign_photo.'('.$md->username().')'.$dep_name.'<br>'.$model->dateThaiTime($md->updated).'</p>');
                $mpdf->WriteHTML('<p>--------------------------------------------------------------------------------</p>',2);
                // $y = $y+60;
                
            }           
        
          
        // The height of the template as it was printed is returned as $actualsize['h']
        // The width of the template as it was printed is returned as $actualsize['w']
        // $mpdf->WriteHTML('Hello World'.$pagecount);
        // $mpdf->WriteHTML('Hello World');
        // $mpdf->WriteHTML( '1qqaaaaaa', 2 );
        // $mpdf->WriteHTML(' '.Url::to('@webroot/'.$model->file).'a');
        // $mpdf->Output();
        $mpdf->Output(Url::to('@webroot/'.$model->file), \Mpdf\Output\Destination::FILE);
        return true;
    } 



}