<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\User;
use app\models\RoleName;

use yii\helpers\Url;

$this->title = $title;
$RoleName = RoleName::find()->where(['status'=>1])->all();
$listRoleName = ArrayHelper::map($RoleName,'id','name');

$User = User::find()->where(['status'=>10])->all();
$listUserProfile= ArrayHelper::map($User,'id','name');

$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
?>
          <!-- Horizontal Form -->
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title"><?=$title?></h3>
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
                
            <?= $form->field($model, 'role_name_id',$fieldOption)
            ->dropDownList(
                $listRoleName,
                ['prompt'=>'เลือกคำนำหน้าชื่อ..']
                )
            ->label($model->getAttributeLabel('role_name_id'),['class'=>'col-sm-2 control-label']) 
        ?>

        <?= $form->field($model, 'user_id',$fieldOption)
            ->dropDownList(
                $listUserProfile,
                ['prompt'=>'เลือกคำนำหน้าชื่อ..']
                )
            ->label($model->getAttributeLabel('user_id'),['class'=>'col-sm-2 control-label']) ;
        ?>
                
           
                                
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