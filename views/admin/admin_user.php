<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;

$this->title = 'สมาชิก';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-role-index box box-primary">
    <div class="box-header with-border">                
    <?= Html::button('เพิ่มข้อมูลสมาชิก', 
                ['value' => Url::to(['/signup']),
                'title' => 'เพิ่มข้อมูลสมาชิก', 
                'class' => 'btn btn-success',
                'id'=>'activity-create-link',
                'data-target' => 'activity-modal'
                ]
                ); ?>
    </div>
    
    <div class="box-body table-responsive">
        
        <table class="table table-bordered">
                <tbody><tr>
                  <th style="width: 10px">#</th>
                  <th>Task</th>
                  <th>Progress</th>
                  <th>Progress</th>
                  <th style="width: 40px">Label</th>
                </tr>
                
                <?php
                    foreach($models as $model){?>
                        <tr>
                            <td><?= $model->id ?></td>
                            <td>
                                <a href="#" data-id="<?=$model->id?>" class="activity-view-link" data-target = "activity-modal"><?=$model->username?></a>
                                <span><?= $model->profile->name ? '('.$model->profile->pfname.$model->profile->name.' '.$model->profile->sname .')': ''?></span>
                                <span class="badge bg-red"><?=$model->status == 9 ? 'ระงับการใช้งาน': ''?></span></td>
                            <td>
                                <!-- <span class="badge bg-red"><?=$model->status?></span> -->
                                <?=$model->profile->dep_name?>
                            </td>
                            <td>
                                <!-- <span class="badge bg-red"><?=$model->status?></span> -->
                                <?=$model->profile->group_work?>
                            </td>
                            <td>
                                <?php if($model->status == 10){
                                    echo '<a href="#" data-id="'.$model->id.'" class="activity-set-deactive btn btn-warning btn-xs" >ระงับการใช้งาน</a>';
                                   
                                }?>
                                <?php if($model->status == 9){
                                    echo '<a href="#" data-id="'.$model->id.'" class="activity-set-active btn btn-success btn-xs" >เปิดใช้งาน</a>';
                                    
                                }?>
                                <a href="<?=Url::to(['/admin/reset_password','id'=>$model->id])?>" onclick="return confirm('Are you sure you want to Reset password?');" class="btn btn-danger btn-xs">Reset password</a>
                                <!-- <a href="#" data-id="<?=$model->id?>" id="activity-reset-password" class=" btn btn-danger btn-xs" onclick="return confirm('Are you sure you want to Reset password?');">Reset Password</a> -->
                            
                            </td>
                            </tr>
                    <?php } ?>
                 
              </tbody>
            </table>
    </div>
</div>


<?php 
// Modal::begin([
//         'id' => 'activity-modal',
//         'header' => '<h4 class="modal-title">สมาชิก</h4>',
//         'size'=>'modal-lg',
//         'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">ปิด</a>',
//         'clientOptions' => [
//             'backdrop' => false, 
//             'keyboard' => true
//             ]
//         ]);
//         Modal::end();
        ?>


<?php $this->registerJs('

function init_click_handlers(){
    $("#activity-create-link").click(function(e) {
            $.get(
                "/signup",
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("เพิ่มข้อมูลสมาชิก");
                    $("#activity-modal").modal("show");
                }
            );
        });
    $(".activity-view-link").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/admin/view",
                {
                    id: fID
                },
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("เปิดดูข้อมูลสมาชิก");
                    $("#activity-modal").modal("show");
                }
            );
        });
        $(".activity-set-active").click(function(e) {
            var fID = $(this).data("id");
                $.get(
                    "/admin/set_active",
                    {
                        id: fID
                    },
                    function (data)
                    {
                        // alert(1234);
                        // $("#activity-modal").find(".modal-body").html(data);
                        // $(".modal-body").html(data);
                        // $(".modal-title").html("แก้ไขข้อมูลสมาชิก");
                        // $("#activity-modal").modal("show");
                    }
                );
        });
        $(".activity-set-deactive").click(function(e) {
            var fID = $(this).data("id");
                $.get(
                    "/admin/set_deactive",
                    {
                        id: fID
                    },
                    function (data)
                    {
                        // alert(1234);
                        // $("#activity-modal").find(".modal-body").html(data);
                        // $(".modal-body").html(data);
                        // $(".modal-title").html("แก้ไขข้อมูลสมาชิก");
                        // $("#activity-modal").modal("show");
                    }
                );
        });
        $("#activity-reset-password").click(function(e) {
            var fID = $(this).data("id");
                $.get(
                    "/admin/reset_password",
                    {
                        id: fID
                    }
                );
        });
    
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>


