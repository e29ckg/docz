<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use app\models\ProfilePfname;
use app\models\UserDepName;
use app\models\UserGroupWork;

$ProfilePfname = ProfilePfname::find()->all();
$listData = ArrayHelper::map($ProfilePfname,'name','name');

$UserDepName = UserDepName::find()->all();
$listDataDepName = ArrayHelper::map($UserDepName,'name','name');

$UserGroupWork = UserGroupWork::find()->all();
$listDataUserGroupWork = ArrayHelper::map($UserGroupWork,'name','name');
/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */

$this->title = 'สมัครสมาชิก';
$fieldOptionUsername = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-user form-control-feedback'></span>"
];
$fieldOptionPass = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-lock form-control-feedback'></span>"
];
$fieldOptionRPass = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-log-in form-control-feedback'></span>"
];
$fieldOptionPFname = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionName = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-user form-control-feedback'></span>"
];
$fieldOptionSname = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-user form-control-feedback'></span>"
];
$fieldOptionDepName = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionUserGroupWork = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionPhone = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='form-control-feedback' ></span>"
];
?>
<div class="login-box">
    <div class="login-logo">
        <a href="#"><?=  Yii::$app->name ?>
        <!-- <b>Admin</b>LTE -->
        </a>
    </div>
    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg">สมัครสมาชิก</p>
    <?php $form = ActiveForm::begin([
        'id' => 'reg-form',
        'enableAjaxValidation' => true,
        'options' => ['enctype' => 'multipart/form-data']
    ]
    ); ?>
    <div class="box-body table-responsive">

        <?= $form->field($model, 'username',$fieldOptionUsername)
            ->textInput(['placeholder' => $model->getAttributeLabel('username')])
            ->label(false) 
        ?>
        <?= $form->field($model, 'password',$fieldOptionPass)
            ->passwordInput(['placeholder' => $model->getAttributeLabel('password')])
            // ->textInput(['placeholder' => $model->getAttributeLabel('password')])
            ->label(false) 
        ?>
        <?= $form->field($model, 'rpassword',$fieldOptionRPass)
            ->passwordInput(['placeholder' => $model->getAttributeLabel('rpassword')])
            // ->textInput(['placeholder' => $model->getAttributeLabel('rpassword')])
            ->label(false) 
        ?>
<hr>
        
        <?= $form->field($model, 'pfname',$fieldOptionPFname)
            ->dropDownList(
                $listData,
                ['prompt'=>'เลือกคำนำหน้าชื่อ..']
                );
            // ->textInput(['placeholder' => $model->getAttributeLabel('pfname'),'maxlength' => true]) 
            // ->label(false) 
        ?>

        <?= $form->field($model, 'name',$fieldOptionName)
            ->textInput(['placeholder' => $model->getAttributeLabel('name'),'maxlength' => true]) ?>

        <?= $form->field($model, 'sname',$fieldOptionSname)
            ->textInput(['placeholder' => $model->getAttributeLabel('sname'),'maxlength' => true]) ?>

        <?= $form->field($model, 'dep_name',$fieldOptionDepName)
            ->dropDownList(
                $listDataDepName,
                ['prompt'=>'เลื่อกตำแหน่ง...']
                );
        ?>

        <?= $form->field($model, 'group_work',$fieldOptionUserGroupWork)
            ->dropDownList(
                $listDataUserGroupWork,
                ['prompt'=>'เลื่อกกลุ่มงาน...']
                );
        ?>
        <?= $form->field($model, 'email')->textInput(['placeholder' => $model->getAttributeLabel('email'),'maxlength' => true]) ?>
        <?= $form->field($model, 'phone')->textInput(['placeholder' => $model->getAttributeLabel('phone'),'maxlength' => true]) ?>
        <?= $form->field($model, 'line_id')->textInput(['placeholder' => $model->getAttributeLabel('line_id'),'maxlength' => true]) ?>
        <?= $form->field($model, 'photo',$fieldOptionPhone)->fileInput() ?>
        <?= $form->field($model, 'sign_photo',$fieldOptionPhone)->fileInput() ?>   

    </div>
    <div class="box-footer">
    <div class="row">
        <div class="col-xs-8">
          
        </div>
        <!-- /.col -->
        <div class="col-xs-4">
          <!-- <button type="submit" class="btn btn-primary btn-block btn-flat">Register</button> -->
          <?= Html::submitButton('Register', ['class' => 'btn btn-primary btn-block btn-flat']) ?>
        </div>
        <!-- /.col -->
      </div>

        
    </div>
    <?php ActiveForm::end(); ?>
</div>
</div>
</div>
