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

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        
        if ($model->load(Yii::$app->request->post())) {      
            $model->user_id = Yii::$app->user->id;
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

    public function actionMg_return($id) //หน้า _mg กดตึกลับ
    {
        $model = DocManage::findOne($id);
        $sort = $model->sort - 1;

        if($sort == 0){
            $DZ = Docz::findOne($model->doc_id);
            $DZ->st = 1;
            $DZ->save();            
                if($DZ->user_profile->line_id){
                    $sms = 'มีหนังสือตีกลับ..'.$model->docz->name_doc();
                    Docz::Line_send($DZ->user_profile->line_id,$sms);
                }
            
        }else{
            $DM = DocManage::find()->where(['doc_id'=>$model->doc_id,'sort'=>$sort])->one();
            $DM->st = 2;
            $DM->save();
            foreach($DM->role_power as $RP){
                if($RP->user_profile->line_id){
                    $sms = '('.$RP->role_name().')'.'มีหนังสือตีกลับ..'. $model->docz->name ;
                    Docz::Line_send($RP->user_profile->line_id,$sms);
                }
            }
        }
        $model->st = 1;
        $model->user_id = Yii::$app->user->id;
        $model->save();
        return $this->redirect(['index','role_name_id'=>$model->role_name_id]);
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
            foreach($DM_next->role_power as $RP){
                if($RP->user_profile->line_id){
                    $sms = '('.$RP->role_name().')'.'มีหนังสือต้องลงชื่อ..'. $DO->name ;
                    Docz::Line_send($RP->user_profile->line_id,$sms);
                }
            }
            $DM_next->save();
        }else{            
            $DO->st = 3;
            $DO->end = date("Y-m-d H:i:s");
            if($DO->user_profile->line_id){
                $sms = '(เจ้าหน้าที่สารบรรณ)'.'หนังสือกลับจากเสนอ.'. $DO->name ;
                Docz::Line_send($DO->user_profile->line_id,$sms);
            }
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