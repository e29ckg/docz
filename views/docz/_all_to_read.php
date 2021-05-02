<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
// var_dump($model->docps);
?>
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <?= $model->doc_speed ?'<small class="label  bg-red">'.$model->doc_speed.'</small>':''?>
                    <?=$model->doc_form_number ? 'ที่ '.$model->doc_form_number : ''?>
                    <?=$model->doc_date ? 'ลงวันที่ '.date("Y-m-d",strtotime($model->doc_date)) : ''?>
                    <?=$model->name ? 'เรื่อง '.$model->name : ''?>
                </h3>
            </div>
            <div class="box-body text-center"> 
                <?php if($model->file){ ?>
                    <?= Url::to('@web/'.$model->file) ?>
                    <embed src="<?= Url::to('@web/'.$model->file) ?>" type="application/pdf" width="100%" height="800px" />
                <?php } ?>
            </div>            
        </div>
        <?php foreach($model->doc_file as $df){ ?>
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">ไฟล์แนบ<?=$df->name?></h3>
            </div>
            <div class="box-body text-center"> 
              <embed src="<?= Url::to('@web/'.$df->file) ?>" type="application/pdf" width="100%" height="600px" />
            </div>            
        </div>  
        <?php } ?>
    </div>
    <div class="col-md-4">
        
        <div>
        <a href="<?=Url::to(Yii::$app->request->referrer)?>" class="btn btn-warning">กลับ</a>
        </div>
    </div>

</div> 


<?php $this->registerJs('

function init_click_handlers(){
    
    $(".activity-send-del").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/docz/send_del",
                {
                    id: fID
                },
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("");
                    $("#activity-modal").modal("show");
                }
            );
        }); 
    // $("#body").addClass("sidebar-collapse");
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>
