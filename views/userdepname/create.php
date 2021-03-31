<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\UserDepName */

$this->title = 'Create User Dep Name';
$this->params['breadcrumbs'][] = ['label' => 'User Dep Names', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-dep-name-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
