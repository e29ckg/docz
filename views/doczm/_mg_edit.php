<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;

$this->title = 'mg_edit';
$listTY =[
    'เรียน ผู้พิพากษาหัวหน้าศาลฯ<br> - เพื่อโปรดทราบ' => '- เรียน ผู้พิพากษาหัวหน้าศาลฯ / - เพื่อโปรดทราบ',
    'เรียน ผู้พิพากษาหัวหน้าศาลฯ<br> - เพื่อโปรดพิจารณา' => '- เรียน ผู้พิพากษาหัวหน้าศาลฯ / - เพื่อโปรดพิจารณา',
    '-ทราบ<br>-ดำเนินการตามเสนอ '=>'- ทราบ / - ดำเนินการตามเสนอ ',
    '- ทราบ'=>'- ทราบ',
    'เรียน ผู้อำนวยการสำนักงานฯ<br> - เพื่อโปรดพิจารณา'=>'เรียน ผู้อำนวยการสำนักงานฯ / - เพื่อโปรดพิจารณา',
    'เรียน ผู้อำนวยการสำนักงานฯ<br> - เพื่อโปรดทราบ'=>'เรียน ผู้อำนวยการสำนักงานฯ / - เพื่อโปรดทราบ'
];
$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
?>
          <!-- Horizontal Form -->
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title"></h3>
        </div>
            <!-- /.box-header -->
            <!-- form start -->
        <!-- <form class="form-horizontal"> -->
        <?php $form = ActiveForm::begin([
            'id' => 'mg_edit-form',
            'enableAjaxValidation' => true,
            'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
        ]
        ); ?>
        <div class="box-body">        
            <?= $form->field($model, 'ty',$fieldOption)
                ->dropDownList(
                    $listTY,
                    ['prompt'=>'เลือก..']
                    )
                ->label($model->getAttributeLabel('ty'),['class'=>'col-sm-2 control-label']) 
            ?>
            <?= $form->field($model, 'detail',$fieldOption)
                ->textarea(['rows' => '6','placeholder' => $model->getAttributeLabel('detail'),'maxlength' => true])
                ->label($model->getAttributeLabel('detail'),['class'=>'col-sm-2 control-label']) ?>
                    
            <!-- /.box-body -->
            <div class="box-footer">              
            
                <button type="submit" class="btn btn-info pull-right">Save</button>
            </div>
            <!-- /.box-footer -->
        <!-- </form> -->
        <?php ActiveForm::end(); ?>
    </div>
          <!-- /.box -->    

          
