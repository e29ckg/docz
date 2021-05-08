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
        
        <table class="table table-bordered" id="user_profile">
                <thead>
                    <tr>
                    <th style="width: 20px">#</th>
                    <th></th>
                    <th></th>
                    <th style="width: 40px"></th>
                    <th style="width: 40px"></th>
                    </tr>
                </thead>
                <tbody>                
                <?php
                    foreach($models as $model){?>
                        <tr>
                        <td><?=$model->sort?></td>
                            <td>
                                <a href="<?=Url::to(['/profile/view','id'=>$model->user_id])?>" >
                                    <img class="profile-user-img img-responsive img-circle" 
                                        src="<?= $model->image($model->photo)?>" alt="User profile picture">
                                </a>
                                <?= $model->sort.','.$model->user_id ?>
                            </td>
                            <td>
                                <a href="<?=Url::to(['/profile/view','id'=>$model->user_id])?>" ><?=$model->getname()?></a>
                                <!-- <a href="#" data-id="<?=$model->user_id?>" class="activity-view-link" data-target = "activity-modal"><?=$model->getname()?></a> -->
                                <span><?= $model->name ? '('.$model->pfname.$model->name.' '.$model->sname .')': ''?></span>
                                <span class="badge bg-red"><?=!($model->user->status == 10) ? 'ระงับการใช้งาน': ''?></span>
                                <p><?=$model->dep_name?><br><?=$model->group_work?></p>
                            </td>
                            <td>
                                
                                <?php if($model->user->status == 10){
                                    echo '<a href="#" data-id="'.$model->user_id.'" class="activity-set-deactive btn btn-warning btn-block" >ระงับการใช้งาน</a>';
                                   
                                }else{
                                    echo '<a href="#" data-id="'.$model->user_id.'" class="activity-set-active btn btn-success btn-block" >เปิดใช้งาน</a>';
                                }?>
                            </td>
                            <td>                               
                                <a href="<?=Url::to(['/admin/reset_password','id'=>$model->user_id])?>" onclick="return confirm('Are you sure you want to Reset password?');" class="btn btn-danger btn-block">Reset password</a>
                                <!-- <a href="#" data-id="<?=$model->user_id?>" id="activity-reset-password" class=" btn btn-danger btn-xs" onclick="return confirm('Are you sure you want to Reset password?');">Reset Password</a> -->
                            
                            </td>
                            </tr>
                    <?php } ?>
              </tbody>
            </table>
    </div>
</div>


<?php $this->registerJs('

function init_click_handlers(){
    $("#activity-create-link").click(function(e) {
            $.get(
                "?r=profile/create_profile",
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
                "?r=admin/view",
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
                    "?r=admin/set_active",
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
                    "?r=admin/set_deactive",
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
                    "?r=admin/reset_password",
                    {
                        id: fID
                    }
                );
        });
    
}
init_click_handlers(); //first run
$("#user_profile").DataTable({
    "order": [[ 0, "asc" ]]
});
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>


