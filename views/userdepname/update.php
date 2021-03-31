<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\UserDepName */

$this->title = 'Update User Dep Name: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'User Dep Names', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="user-dep-name-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>