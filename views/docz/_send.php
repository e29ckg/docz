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
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><?=$model->name?></h3>
            </div>
            <div class="box-body text-center"> 
                <?php if($model->file){ ?>
                    <?= Url::to(['@web/'.$model->file]) ?>
                    <embed src="<?= Url::to(['@web/'.$model->file]) ?>" type="application/pdf" width="100%" height="600px" />
                <?php } ?>
            </div>            
        </div>
        <?php foreach($model->doc_file as $df){ ?>
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">ไฟล์แนบ<?=$df->name?></h3>
            </div>
            <div class="box-body text-center"> 
              <embed src="<?= Url::to(['@web/'.$df->file]) ?>" type="application/pdf" width="100%" height="600px" />
            </div>            
        </div>  
        <?php } ?>
    </div>
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-body">            
                <ul class="timeline timeline-inverse">
                    <!-- <li class="time-label">
                        <span class="bg-red">
                            10 Feb. 2014
                        </span>
                    </li> -->
                    <?php foreach($model->doc_manage as $dm){ ?>
                
                    <li>
                        <?php if($dm->st == 3){?>
                            <i class="fa fa-check bg-red"></i>
                        <?php }else if($dm->st == 2){ ?>
                            <i class="fa fa-comments-o bg-yellow"></i>
                        <?php }else{ ?>
                            <i class="fa fa-user bg-aqua bg-blue"></i>
                        <?php } ?>

                        <div class="timeline-item">
                            <span class="time"><i class="fa fa-clock-o"></i><?=$dm->updated?></span>

                            <h3 class="timeline-header">
                                <a href="#"><?=$dm->role_name()?></a>
                            </h3>
                            
                        </div>
                    </li>
                    <!-- END timeline item --> 
                    <?php } ?>

                    <li>
                    <i class="fa fa-clock-o bg-gray"></i>
                    <div class="timeline-item">
                            <!-- <span class="time"><i class="fa fa-clock-o"></i>12345555 </span> -->

                            <!-- <h3 class="timeline-header">
                            
                            </h3> -->

                            <!-- <div class="timeline-body">

                            </div> -->
                            <div class="timeline-footer">
                            <!-- <a class="btn btn-primary btn-xs">Read more</a> -->

                            <a href="<?= Url::to(['docz/start','id'=> $model->id ])?>" data-method="post" class="activity-start btn btn-primary btn-block">เริ่ม</a>
                            </div>
                        </div>
                    </li>
                </ul>    
            </div>
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
    $("#body").addClass("sidebar-collapse");
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>
