<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\User;
use app\models\Docz;
use app\models\Role;
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

    public function actionIndex($role_name_id)
    {
        $models = DocManage::find()->where(['st'=>2,'role_name_id'=>$role_name_id])->orderBy(['id'=>SORT_DESC])->all();
        return $this->render('index',[
            'models' => $models,
            'role_name_id' =>$role_name_id
        ]);
    }

    public function actionMg($doc_id,$role_name_id) //ดำเนินการเรื่องเดียว
    {
        $model = Docz::findOne($doc_id);
        
        // return $this->render('_mg',[
        //     'model' => $model
        // ]);
        $modelDM = DocManage::find()->where(['doc_id'=>$doc_id,'role_name_id'=>$role_name_id])->one();
        
        if ($modelDM->load(Yii::$app->request->post())) {      
            $modelDM->user_id = Yii::$app->user->id;
            if($modelDM->save()){
                Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
                // return $this->redirect(['mg','doc_id'=>$modelDM->doc_id,'role_name_id'=>$role_name_id]);
                // return $this->redirect(['index','role_name_id'=>$role_name_id]);
                return $this->redirect(['send','doc_id'=>$doc_id,'role_name_id'=>$role_name_id]);
            }
        }

        return $this->render('_mg',[
                'model' => $model,
                'modelDM' => $modelDM,
                'role_name_id' =>$role_name_id
            ]);
    }

    // public function actionMg_edit($id) //หน้า _mg กด 
    // {
    //     $model = DocManage::findOne($id);

    //     if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
    //         Yii::$app->response->format = Response::FORMAT_JSON;
    //         return ActiveForm::validate($model);
    //     }
        
    //     if ($model->load(Yii::$app->request->post())) {      
    //         $model->user_id = Yii::$app->user->id;
    //         if($model->save()){
    //             Yii::$app->session->setFlash('success', 'บันทักข้อมูลเรียบร้อย');
    //             return $this->redirect(['mg','id'=>$model->doc_id]);
    //         }

    //     }

    //     if(Yii::$app->request->isAjax){
    //         return $this->renderAjax('_mg_edit',[
    //             'model' => $model,
    //         ]);
    //     } 
    //     return $this->render('_mg_edit',[
    //         'model' => $model
    //     ]);
    // }

    public function actionMg_return($id) //หน้า _mg กดตึกลับ
    {
        $model = DocManage::findOne($id);
        $sort = $model->sort - 1;

        if($sort == 0){
            $DZ = Docz::findOne($model->doc_id);
            $DZ->st = 1;
            $DZ->save();            
                if($DZ->user_profile->line_id){
                    $sms = 'มีหนังสือตีกลับ : '.$model->docz->name_doc();
                    Docz::Line_send($DZ->user_profile->line_id,$sms);
                }
            
        }else{
            $DM = DocManage::find()->where(['doc_id'=>$model->doc_id,'sort'=>$sort])->one();
            $DM->st = 2;
            $DM->save();
            foreach($DM->role_power as $RP){
                if($RP->user_profile->line_id){
                    $sms = '('.$RP->role_name().')'.'มีหนังสือตีกลับ :'. $model->docz->name ;
                    Docz::Line_send($RP->user_profile->line_id,$sms);
                }
            }
        }
        $model->st = 1;
        $model->user_id = Yii::$app->user->id;
        $model->save();
        return $this->redirect(['index','role_name_id'=>$model->role_name_id]);
    }

    public function actionSend($doc_id,$role_name_id) //หน้า _mg กด 
    {
        
        $DM = DocManage::find()->where(['doc_id'=>$doc_id,'role_name_id'=>$role_name_id])->one();
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
            foreach($DM_next->role_power as $RP){
                if($RP->user_profile->line_id){
                    $sms = '('.$RP->role_name().')'.'มีหนังสือต้องลงชื่อ : '. $DO->name ;
                    Docz::Line_send($RP->user_profile->line_id,$sms);
                }
            }
            $DM_next->save();
        }else{            
            $DO->st = 3;
            $DO->end = date("Y-m-d H:i:s");
            if($this->stamp_end($DO->id)){
                Yii::$app->session->setFlash('success', 'stamp_end');
            }      //stapEnd
            if($DO->user_profile->line_id){
                $sms = '(เจ้าหน้าที่สารบรรณ)'.'หนังสือกลับจากเสนอ.'. $DO->name ;
                Docz::Line_send($DO->user_profile->line_id,$sms);
            }
        }
        if($DO->save()){
            Yii::$app->session->setFlash('success', 'ส่งต่อแล้วจ้า');
            return $this->redirect(['index','role_name_id'=>$role_name_id]);
        }           
        
        return $this->render('_mg',[
            'model' => $model
        ]);
    }

    public function stamp_end($id){
        $model = Docz::findOne($id);
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'default_font' => 'garuda'
        ]);

        $html = '<p>hi world สวัสดี</p>';
        $stylesheet = file_get_contents(Url::to('@webroot/css/pdf.css')); // external css
        $mpdf->WriteHTML($stylesheet,1);

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
            $mpdf->SetXY(20,5);
            $keyword = '';
            foreach($model->doc_manage_asc as $md){

                // $mpdf->SetXY(20,5);
                // $mpdf->SetFont('garuda', '', 8);
                $mpdf->WriteHTML($stylesheet,1);
                $mpdf->WriteHTML('<p id="hh">'.$md->ty.'</p>',2);
                // $mpdf->SetFont('garuda', '', 8);
                $mpdf->WriteHTML('<pre>'.$md->detail.'</pre>');
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
                $keyword .= '['.$md->username().':'.$md->dep_name().'] ';
            }           
        // $mpdf->SetTitle($model->name);
        // $mpdf->SetAuthor('ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์');        
        // $mpdf->SetKeywords($keyword );
        $mpdf->Output(Url::to('@webroot/'.$model->file), \Mpdf\Output\Destination::FILE);
        // $mpdf->Output();
        return true;
    } 


}