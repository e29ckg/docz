<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\Url;
use kartik\select2\Select2;

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model \common\models\LoginForm */

$this->title = 'Sign In';

$fieldOptions1 = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-user form-control-feedback'></span>"
];

$fieldOptions2 = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-lock form-control-feedback'></span>"
];

$fieldOptions3 = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-envelope form-control-feedback'></span>"
];
$fieldOptions4 = [
    'options' => ['class' => 'form-group'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-envelope form-control-feedback'></span>"
];

$data =[1=>'ssss'];
?>
<div class="login-box">
    <div class="login-logo">
        <a href="#"><b>Admin</b>LTE</a>
    </div>
    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg">SignUp</p>
            <?php $form = ActiveForm::begin(['id' => 'form-signup']); ?>

                <?= $form
                    ->field($model, 'username', $fieldOptions1)
                    // ->label(false)
                    ->textInput([
                        'autofocus' => true,
                        'placeholder' => $model->getAttributeLabel('username')]) ?>

                <?= $form->field($model, 'email', $fieldOptions3) ?>

                <?= $form->field($model, 'password',$fieldOptions2)
                    // ->label(false)
                    ->passwordInput() ?>

                <?= $form->field($model, 'name') ?>

                <?php //$form->field($model, 'dep') ?>

                <?= $form->field($model, 'dep',$fieldOptions4)->widget(Select2::classname(), [
                    'data' => [1 =>'Alaska',3=>'Delaware'],
                    'options' => [
                        'placeholder' => 'Select a state ...',
                        'class' =>'form-control select2'],
                    'pluginOptions' => [

                        'allowClear' => true
                    ],
                ]);?>


                <div class="form-group">
                    <?= Html::submitButton('Signup', ['class' => 'btn btn-primary', 'name' => 'signup-button']) ?>
                </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
