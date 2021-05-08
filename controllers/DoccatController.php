<?php

namespace app\controllers;

use Yii;
use app\models\Docz;
use app\models\DocCat;
use app\models\DocCatName;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\bootstrap\ActiveForm;
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
        $models = DocCatName::find()->all();

        return $this->render('index', [
            'models' => $models
        ]);
    }

    public function actionIndex_out($doc_cat_name_id = null)
    {
        $models = Docz::find()->where(['st'=>4])->all();
        $count = 0;
        $data = [];
        foreach($models as $model){
            $doc_count = DocCat::find()->where(['doc_id'=>$model->id])->count();
            if($doc_count == 0){
                $data[] = [
                    'doc_id' => $model->id,
                    'name' => $model->name_doc()
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
        return $this->render('view', [
            'model' => $this->findModel($id),
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
}
