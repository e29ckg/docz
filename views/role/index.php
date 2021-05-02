<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;
$this->title = 'กำหนดสิทธ์';
?>

<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">ผู้มีอำนาจเซนต์(วันนี้)</h3>
                <div class="box-tools">
                    <button id="activity-create-role-power" class="btn btn-success">เพิ่ม</button>
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th>สิทธิ์</th>
                            <th>ชื่อผู้รับผิดชอบ</th>
                            <th style="width: 100px"></th>
                        </tr>
                        <?php foreach($models_role_power as $role_power){ ?>
                        <tr>
                            <td><?= $role_power->role_name->id?></td>
                            <td><?=$role_power->role_name->name?></td>
                            <td><?=$role_power->user->name?></td>
                            <td>
                                <button data-id="<?=$role_power->id?>" class="activity-update-role-power btn btn-warning btn-xs">แก้ไข</button>
                                <a href="<?= Url::to(['/role/role_power_del','id'=>$role_power->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-xs">ลบ</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <!-- /.box-body -->
        </div>
    </div>

<!-- models_Role_Name ชื่อสิทธ์-->
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">ชื่อสิทธิ์</h3>
                <div class="box-tools">
                    <button id="activity-create-role-name" class="btn btn-success">เพิ่ม</button>
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th>ชื่อ</th>
                            <th style="width: 100px">จัดการ</th>
                        </tr>
                        <?php foreach($models_role_name as $role_name){ ?>
                        <tr>
                            <td><?= $role_name->id ?></td>
                            <td><?= $role_name->name ?></td>
                            <td>
                                <button data-id="<?=$role_name->id?>" class="activity-update-role-name btn btn-warning btn-xs">แก้ไข</button>
                                <a href="<?= Url::to(['/role/role_name_del','id'=>$role_name->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-xs">ลบ</a>
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
    $("#activity-create-role-name").click(function(e) {
            $.get(
                "?r=role/role_name_create",
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("");
                    $("#activity-modal").modal("show");
                }
            );
        });
    $(".activity-update-role-name").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=role/role_name_update",
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

        $("#activity-create-role-power").click(function(e) {
            $.get(
                "?r=role/role_power_create",
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
                "?r=role/role_power_update",
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