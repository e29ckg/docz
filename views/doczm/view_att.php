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
              <h3 class="box-title"><?=$model->name?></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body text-center"> 
              <embed src="<?= Url::to('@web/'.$model->file) ?>" type="application/pdf" width="100%" height="600px" />
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
              <a href="<?= Url::to(['/docz/del_att','id'=>$model->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-flat">ลบ</a>
                           
            </div>
          </div>
          <!-- /.box -->
        </div>
</div>