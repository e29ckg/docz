<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'User Profiles';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">DocProfile</h3>
                <div class="box-tools">
                    <!-- <?= Html::a('Create', ['create'], ['class' => 'btn btn-success btn-flat']) ?> -->
                    <button id="activity-create" class="btn btn-success btn-flat">เพิ่ม</button>
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th>วันที่</th>
                            <th>ชื่อ</th>
                            <th>ขั้นตอน</th>
                            <th style="width: 100px"></th>
                        </tr>
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?= $model->id?></td>
                            <td><?= date("Y-m-d", strtotime("$model->doc_date"));?></td>
                            <td>
                            <?= date("Y-m-d", strtotime("$model->doc_date"));?>
                                <?=$model->doc_date?>
                                <?=$model->name?>
                                <button data-id="<?=$model->id?>" class="activity-view btn btn-success btn-xs">view</button>
                                <?= Html::a('view', ['view','id'=>$model->id], ['class' => 'btn btn-success btn-flat']) ?>
                            </td>
                            <td>                                
                                
                                     </td>
                            <td>
                            <button data-id="<?=$model->id?>" class="activity-update-doc-profile btn btn-warning btn-xs">แก้ไข</button>
                              
                                <!-- <button data-id="<?=$model->id?>" class="activity-update-role-power btn btn-warning btn-xs">แก้ไข</button> -->
                                <a href="<?= Url::to(['/bsdr/del','model_id'=>$model->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" data-method="post" class="btn btn-danger">ลบ</a>
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
    $("#activity-create").click(function(e) {
            $.get(
                "/bsdr/create",
                function (data)
                {
                    $("#activity-modal").find(".modal-body").html(data);
                    $(".modal-body").html(data);
                    $(".modal-title").html("");
                    $("#activity-modal").modal("show");
                }
            );
        });
    $(".activity-view").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/bsdr/view",
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