<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;


$this->title = 'เปลี่ยนรหัสผ่าน';

$fieldOptionPass = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-lock form-control-feedback'></span>"
];
$fieldOptionRPass = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='glyphicon glyphicon-log-in form-control-feedback'></span>"
];
?>
<div class="login-box">    
    <!-- /.login-logo -->
    <div class="login-box-body">
    <?php $form = ActiveForm::begin([
        'id' => 'change-password-form',
        'enableAjaxValidation' => true,
    ]
    ); ?>
    <div class="box-body table-responsive">
        <?= $form->field($model, 'id')->hiddenInput(['value'=> $model->id])->label(false);?>
        <?= $form->field($model, 'password',$fieldOptionPass)
            ->passwordInput(['placeholder' => $model->getAttributeLabel('password')])
            // ->textInput(['placeholder' => $model->getAttributeLabel('password')])
            ->label(false) 
        ?>
        <?= $form->field($model, 'rpassword',$fieldOptionRPass)
            ->passwordInput(['placeholder' => 'Retype Password'])
            // ->textInput(['placeholder' => $model->getAttributeLabel('rpassword')])
            ->label(false) 
        ?>

    </div>
    <div class="box-footer">
    <div class="row">
        <div class="col-xs-8">
          
        </div>
        <!-- /.col -->
        <div class="col-xs-4">
          <!-- <button type="submit" class="btn btn-primary btn-block btn-flat">Register</button> -->
          <?= Html::submitButton('Update', ['class' => 'btn btn-primary btn-block btn-flat']) ?>
        </div>
        <!-- /.col -->
      </div>

        
    </div>
    <?php ActiveForm::end(); ?>
</div>
</div>
</div>
