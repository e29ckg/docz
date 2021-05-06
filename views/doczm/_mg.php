<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
// var_dump($model->docps);
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

<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <?=$model->name_doc()?>
                </h3>
            </div>
            <div class="box-body text-center"> 
                <?php if($model->file){ ?>
                    <embed src="<?= Url::to('@web/'.$model->file) ?>" type="application/pdf" width="100%" height="600px" />
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
        <div class="box box-primary">
            <div class="box-body">            
                <ul class="timeline timeline-inverse">
               
                    <?php foreach($model->doc_manage as $dm){ ?>
                
                    <li>
                        <?php if($dm->st == 3){?>
                            <i class="fa fa-check bg-red"></i>
                        <?php }else if($dm->st == 2){ ?>
                            <i class="fa fa-comments-o bg-yellow"></i>
                        <?php }else{ ?>
                            <i class="fa fa-user bg-aqua bg-blue"></i>
                        <?php  } ?>

                        <div class="timeline-item">
                            <span class="time"><i class="fa fa-clock-o"></i><?=$dm->updated?></span>

                            <h3 class="timeline-header">
                                <a href="#"><?=$dm->role_name()?></a>
                            </h3>

                            <?php if($dm->st == 2){ ?>
                               
                                <div class="timeline-body">
                                    <!-- <p style="white-space: pre-line"><b><?=$dm->ty?></b></p>
                                    <pre style="white-space: pre-line"><?=$dm->detail?></pre>
                                    <p style="white-space: pre-line"><?=$dm->username()?></p> -->
                                    <div >
                                        <?php $form = ActiveForm::begin([
                                        'id' =>  'mg_edit-form',
                                        // 'enableAjaxValidation' => true,
                                        // 'options' => [
                                        //     'enctype' => 'multipart/form-data',
                                        //     'class'=>'form-horizontal']
                                        ]); ?>
                                        <?= $form->field($modelDM, 'ty')
                                            ->dropDownList(
                                            $listTY,
                                            ['prompt'=>'เลือกตรายาง'])
                                            ->label(false) 
                                        ?>
                                    <?= $form->field($modelDM, 'detail')
                                    ->textarea(['rows' => '6','placeholder' => $modelDM->getAttributeLabel('detail'),'maxlength' => true])
                                    ->label(false) ?>
                                    
                                    </div>
                                </div>

                                    
                                <div class="timeline-footer">
                                        <button type="submit" class="btn btn-info" onclick="return confirm('บันทึกและส่งต่อ ใช่ไหม ?');" >บันทึก / ส่งต่อ</button>                           
                                    
                                     <!-- <a data-id="<?=$dm->id?>" class="activity-mg-edit btn btn-warning ">ลงชื่อ</a> -->
                                    
                                    <a href="<?=Url::to(['/doczm/mg_return','id'=>$dm->id])?>" onclick="return confirm('เอกสารนี้ต้องการตีกลับใช่ไหม ?');" class="btn btn-danger btn-xs">ตีกลับเอกสาร</a>
                                </div>
                                <?php ActiveForm::end(); ?>
                            <?php }else if($dm->st == 3){ ?>
                                <div class="timeline-body">
                                    <p style="white-space: pre-line"><b><?=$dm->ty?></b></p>
                                    <pre style="white-space: pre-line"><?=$dm->detail?></pre>
                                    <p class="text-right" style="white-space: pre-line"><?=$dm->username()?></p>
                                </div>
                                <div class="timeline-footer">
                                    
                                </div>
                            <?php } ?>
                        </div>
                    </li>
                    <!-- END timeline item --> 
                    <?php } ?>
                    
                    <li>
                    <i class="fa fa-clock-o bg-gray"></i>
                    
                    </li>
                </ul>    
            </div>
        </div>
        <div>
            <a href="<?=Url::to(Yii::$app->request->referrer)?>" class="btn btn-warning">กลับ</a>
        </div>
    </div>
</div> 

<?php $this->registerJs('

function init_click_handlers(){
    
    $(".activity-mg-edit").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=doczm/mg_edit",
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
    // $(".activity-mg-return").click(function(e) {
    //         var fID = $(this).data("id");
    //         // alert(fID);
    //         $.get(
    //             "/doczm/mg_return",
    //             {
    //                 id: fID
    //             },
    //             function (data)
    //             {
    //                 $("#activity-modal").find(".modal-body").html(data);
    //                 $(".modal-body").html(data);
    //                 $(".modal-title").html("");
    //                 $("#activity-modal").modal("show");
    //             }
    //         );
    //     }); 
    $("#body").addClass("sidebar-collapse");
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>
