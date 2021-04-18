<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\User;
use app\models\RoleName;

use yii\helpers\Url;

$this->title = 'Doc Profile';

$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
?>
          <!-- Horizontal Form -->
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title"><?=$this->title?></h3>
        </div>
            <!-- /.box-header -->
            <!-- form start -->
        <!-- <form class="form-horizontal"> -->
        <?php $form = ActiveForm::begin([
            'id' => 'reg-form',
            'enableAjaxValidation' => true,
            'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
        ]
        ); ?>
            <div class="box-body">                
            <?= $form->field($model, 'name',$fieldOption)
                    ->textInput([
                        'placeholder' => $model->getAttributeLabel('name'),
                        'maxlength' => true
                        ])
                    ->label($model->getAttributeLabel('name'),['class'=>'col-sm-2 control-label']) ?>
                                
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
                <button type="submit" class="btn btn-info pull-right">Submit</button>
            </div>
            <!-- /.box-footer -->
        <!-- </form> -->
        <?php ActiveForm::end(); ?>
    </div>
          <!-- /.box -->    