<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;

/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */

$this->title = '';
// $this->params['breadcrumbs'][] = ['label' => 'Profiles', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$dataRole = ['ddd','aaaa','dddd'];
?>
<div class="row">
<div class="col-md-12">
          <div class="box">
            <div class="box-header with-border">
              <h3 class="box-title"><?=$model->id?><?= $model->doc_date . ' ' .$model->name ?></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body text-center"> 
              <embed src="<?= Url::to('@web/'.$model->file) ?>" type="application/pdf" width="100%" height="600px" />
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
</div>