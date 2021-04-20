<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\RoleName;

use yii\helpers\Url;

$this->title = 'ขั้นตอนการทำงาน';
$RoleName = RoleName::find()->where(['status'=>1])->all();
$listRoleName = ArrayHelper::map($RoleName,'id','name');

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
                
            <?= $form->field($model, 'role_name_id',$fieldOption)
            ->dropDownList(
                $listRoleName,
                ['prompt'=>'เลือก..']
                )
            ->label($model->getAttributeLabel('role_name_id'),['class'=>'col-sm-2 control-label']) 
        ?>
                                
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
                <button type="submit" class="btn btn-info pull-right">Submit</button>
            </div>
            <!-- /.box-footer -->
        <!-- </form> -->
        <?= $form->field($model, 'doc_profile_id')->hiddenInput(['value' => $doc_profile_id])->label(false) ?>
        <?= $form->field($model, 'code')->hiddenInput(['value' => $code])->label(false) ?>
        <?php ActiveForm::end(); ?>
    </div>
          <!-- /.box -->    