<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="user-profile-form box box-primary">
    <?php $form = ActiveForm::begin(); ?>
    <div class="box-body table-responsive">

        <?= $form->field($model, 'user_id')->textInput(['value' => Yii::$app->user->identity->id]) ?>

        <?= $form->field($model, 'pfname')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'sname')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'dep_name')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'group_work')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'photo')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'sign_photo')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'created_at')->textInput() ?>

    </div>
    <div class="box-footer">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success btn-flat']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
