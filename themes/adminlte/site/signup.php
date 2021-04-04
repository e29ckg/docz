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
                        'class' =>'form-control'],
                    'pluginOptions' => [

                        'allowClear' => true
                    ],
                ]);?>

                <div class="form-group" data-select2-id="28">
                <label>Minimal</label>
                <select class="form-control select2 select2-hidden-accessible" style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true">
                  <!-- <option selected="selected" data-select2-id="3">Alabama</option> -->
                  <option data-select2-id="30">Alaska</option>
                  <option data-select2-id="31">California</option>
                  <option data-select2-id="32">Delaware</option>
                  <option data-select2-id="33">Tennessee</option>
                  <option data-select2-id="34">Texas</option>
                  <option data-select2-id="35">Washington</option>
                </select>
                <!-- <span class="select2 select2-container select2-container--default select2-container--below select2-container--focus" dir="ltr" data-select2-id="2" style="width: 100%;"> -->
                <!-- <span class="selection"> -->
                <!-- <span class="select2-selection select2-selection--single" role="combobox" aria-haspopup="true" aria-expanded="false" tabindex="0" aria-labelledby="select2-xfq0-container"> -->
                <!-- <span class="select2-selection__rendered" id="select2-xfq0-container" role="textbox" aria-readonly="true" title="Alabama">Alabama</span> -->
                <!-- <span class="select2-selection__arrow" role="presentation"><b role="presentation"></b></span> -->
                <!-- </span> -->
                <!-- </span> -->
                <!-- <span class="dropdown-wrapper" aria-hidden="true"></span> -->
                <!-- </span> -->
              </div>


                <div class="form-group">
                    <?= Html::submitButton('Signup', ['class' => 'btn btn-primary', 'name' => 'signup-button']) ?>
                </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
