<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\RoleName;

use yii\helpers\Url;

$this->title = $title;
$RoleName = RoleName::find()->where(['status'=>1])->all();
$listRoleName = ArrayHelper::map($RoleName,'id','name');

$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
$fieldOptionDis = [
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
                
            <?= $form->field($model, 'role_name_id',$fieldOptionDis)
            ->dropDownList(
                $listRoleName,
                ['prompt'=>'...','disabled'=>'']
                )
            ->label($model->getAttributeLabel('role_name_id'),['class'=>'col-sm-2 control-label']) 
        ?>
        <?= $form->field($model, 'name_dep1',$fieldOption)
            ->textInput(['placeholder' => $model->getAttributeLabel('name_dep1'),'maxlength' => true])
            ->label($model->getAttributeLabel('name_dep1'),['class'=>'col-sm-2 control-label']);  ?>

        <?= $form->field($model, 'name_dep2',$fieldOption)
            ->textInput(['placeholder' => $model->getAttributeLabel('name_dep2'),'maxlength' => true])
            ->label($model->getAttributeLabel('name_dep2'),['class'=>'col-sm-2 control-label']); ?>
            
        <?= $form->field($model, 'name_dep3',$fieldOption)
            ->textInput(['placeholder' => $model->getAttributeLabel('name_dep3'),'maxlength' => true])
            ->label($model->getAttributeLabel('name_dep3'),['class'=>'col-sm-2 control-label']); ?>
                                
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
            <?php if($title == 'แก้ไขตำแหน่ง'){?>
                <a href="<?=Url::to(['/profile/role_del','id'=>$model->id])?>" class="btn btn-danger ">ลบ</a>
            <?php } ?>
            
                <button type="submit" class="btn btn-info pull-right">Submit</button>
            </div>
            <!-- /.box-footer -->
        <!-- </form> -->
        <?= $form->field($model, 'user_id')->hiddenInput(['value' => $id])->label(false) ?>
        <?php ActiveForm::end(); ?>
    </div>
          <!-- /.box -->    