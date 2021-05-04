<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use app\models\ProfilePfname;
use app\models\UserDepName;
use app\models\UserGroupWork;
use yii\helpers\Url;

$ProfilePfname = ProfilePfname::find()->all();
$listData = ArrayHelper::map($ProfilePfname,'name','name');

$UserDepName = UserDepName::find()->all();
$listDataDepName = ArrayHelper::map($UserDepName,'name','name');

$UserGroupWork = UserGroupWork::find()->all();
$listDataUserGroupWork = ArrayHelper::map($UserGroupWork,'name','name');
/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
$this->title = 'แก้ไขข้อมูล';
$fieldOptionPFname = [
    'options' => ['class' => 'form-group has-feedback col-md-4'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionName = [
    'options' => ['class' => 'form-group has-feedback col-md-4'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionSname = [
    'options' => ['class' => 'form-group has-feedback col-md-4'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionDepName = [
    'options' => ['class' => 'form-group has-feedback col-md-6'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionUserGroupWork = [
    'options' => ['class' => 'form-group has-feedback col-md-6'],
    'inputTemplate' => "{input}<span class='form-control-feedback' ></span>"
];
$fieldOptionPhone = [
    'options' => ['class' => 'form-group has-feedback col-md-6'],
    'inputTemplate' => "{input}<span class='form-control-feedback' ></span>"
];
$fieldOptionLine_id= [
    'options' => ['class' => 'form-group has-feedback col-md-6'],
    'inputTemplate' => "{input}<span class='form-control-feedback' ></span>"
];
?>
<div class="row">    
    
    <?php $form = ActiveForm::begin([
        'id' => 'reg-form',
        'enableAjaxValidation' => true,
        'options' => ['enctype' => 'multipart/form-data']
    ]
    ); ?>
    <div class="box-body">              
        <div class="row">
            <?= $form->field($model, 'pfname',$fieldOptionPFname)
                ->dropDownList(
                    $listData,
                    ['prompt'=>'เลือกคำนำหน้าชื่อ..']
                    ); 
            ?>
            <?= $form->field($model, 'name',$fieldOptionName)
                ->textInput(['placeholder' => $model->getAttributeLabel('name'),'maxlength' => true]) ?>
            
            <?= $form->field($model, 'sname',$fieldOptionSname)
                ->textInput(['placeholder' => $model->getAttributeLabel('sname'),'maxlength' => true]) ?>
        </div>   
        <div class="row">
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
        </div>
        <div class="row">
            <?= $form->field($model, 'phone',$fieldOptionPhone)->textInput(['placeholder' => $model->getAttributeLabel('phone'),'maxlength' => true]) ?>
            <?= $form->field($model, 'line_id',$fieldOptionPhone)->textInput(['placeholder' => $model->getAttributeLabel('line_id'),'maxlength' => true]) ?>
        </div>
        <div class="row">
        
            <div class="col-md-6">
                <img src="<?=$model->image($model->photo)?>" alt="photo"class="profile-user-img img-responsive img-circle">  <br>
                <?= $form->field($model, 'photo',$fieldOptionPhone)->fileInput() ?>
            </div>
            <div class="col-md-6">
            <img src="<?=$model->image($model->sign_photo)?>" alt="photo"class="profile-user-img img-responsive img-circle">  <br>
                
                <?= $form->field($model, 'sign_photo',$fieldOptionPhone)->fileInput() ?>
            </div>
        </div>

    </div>
    <div class="box-footer">
    <div class="row">
        <div class="col-xs-8">
          
        </div>
        <!-- /.col -->
        <div class="col-xs-4">
          <!-- <button type="submit" class="btn btn-primary btn-block btn-flat">Register</button> -->
          <?= Html::submitButton('ปรับปรุง', ['class' => 'btn btn-primary btn-block btn-flat']) ?>
        </div>
        <!-- /.col -->
      </div>

        
    </div>
    <?php ActiveForm::end(); ?>
</div>
</div>
