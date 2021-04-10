<?php

namespace app\controllers;

use Yii;
use app\models\UserProfile;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

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
            // 'access' => [
            //     'class' => \yii\filters\AccessControl::className(),
            //     'only' => ['index','create', 'update'],
            //     'rules' => [
            //         // deny all POST requests
            //         [
            //             'allow' => false,
            //             'verbs' => ['POST']
            //         ],
            //         // allow authenticated users
            //         [
            //             'allow' => true,
            //             'roles' => ['@'],
            //         ],
            //         // everything else is denied
            //     ],
            // ],
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
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
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

    /**
     * Updates an existing UserProfile model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id = null)
    {
        // $id = ;
        $model = $this->findModel(['user_id' => Yii::$app->user->identity->id]);
        if(!$model){
            return $this->redirect(['create']);
        }
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pr = UserProfile::findOne(['user_id' => Yii::$app->user->identity->id]);
                if($pr){
                    Yii::$app->session->set('profile',[
                        'user_id' => $pr->user_id,
                        'name' => $pr->name,   
                        'dep_name' => $pr->dep_name                     
                        ]);
                }
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing UserProfile model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
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
}
