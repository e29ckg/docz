<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;
$this->title = 'กำหนดสิทธ์';
?>

<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">DocProfile</h3>
                <div class="box-tools">
                    <button id="activity-create-doc-profile" class="btn btn-success">เพิ่ม</button>
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th>ชื่อ</th>
                            <th>ขั้นตอน</th>
                            <th style="width: 100px"></th>
                        </tr>
                        <?php foreach($models as $doc_profile){ ?>
                        <tr>
                            <td><?= $doc_profile->id?></td>
                            <td>
                                <?=$doc_profile->name?>
                                <button data-id="<?=$doc_profile->id?>" class="activity-update-doc-profile btn btn-warning btn-xs">แก้ไข</button>
                                
                            </td>
                            <td>
                                <?php 
                                // echo count($doc_profile->doccps);
                                if(count($doc_profile->doccps) > 0){
                                    echo '#';
                                    foreach($doc_profile->doccps as $doc_profile_sub){ 
                                        echo ' -> '.$doc_profile_sub->rolename->name;
                                    } 
                                }
                                ?>  
                                <?php if(count($doc_profile->doccps) > 0){ ?>                  
                                    <a href="<?= Url::to(['/docprofile/doc_profile_sub_del','doc_profile_id'=>$doc_profile->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-xs">ลบ</a>
                                <?php } ?>
                                <button data-id="<?=$doc_profile->id?>" class="activity-create-doc-profile-sub btn btn-success btn-xs">เพิ่ม</button>
                            </td>
                            <td>
                                <!-- <button data-id="<?=$doc_profile->id?>" class="activity-update-role-power btn btn-warning btn-xs">แก้ไข</button> -->
                                <a href="<?= Url::to(['/docprofile/doc_profile_del','doc_profile_id'=>$doc_profile->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" data-method="post" class="btn btn-danger">ลบ</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <!-- /.box-body -->
        </div>
    </div>


</div>

<?php $this->registerJs('

function init_click_handlers(){
    $("#activity-create-doc-profile").click(function(e) {
            $.get(
                "/docprofile/doc_profile_create",
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("");
                    $("#activity-modal").modal("show");
                }
            );
        });
    $(".activity-update-doc-profile").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/docprofile/doc_profile_update",
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

        $(".activity-create-doc-profile-sub").click(function(e) {
            var fID = $(this).data("id");
            $.get(
                "/docprofile/doc_profile_sub_create",
                {
                    doc_profile_id: fID
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
        $(".activity-update-role-power").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/role/$doc_profile_update",
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
    
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>