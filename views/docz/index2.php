<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'รายงานวันทำการประจำวัน';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"></h3>
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
                            <th>ไฟล์</th>
                            <th style="width: 150px"></th>
                        </tr>
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?= $model->id?></td>
                            <td><?= date("d-Y-m", strtotime("$model->doc_date"));?></td>
                            <td>
                                <?=$model->doc_date?>
                                <?=$model->name?>
                                <!-- <button data-id="<?=$model->id?>" class="activity-view btn btn-success btn-flat">view</button> -->
                                <!-- <?= Html::a('view', ['view','id'=>$model->id], ['class' => 'btn btn-success btn-flat']) ?> -->
                            </td>
                            <td>   
                                <?php if($model->bsdr_file){?>
                                    <a href="#" data-id="<?=$model->id?>" class="activity-view btn btn-success btn-flat"><i class="fa fa-file-pdf-o " aria-hidden="true"></i></a>
                                
                                <?php } ?>
                            </td>
                            <td>
                            <button data-id="<?=$model->id?>" class="activity-update btn btn-warning btn-flat">แก้ไข</button>
                              
                                <!-- <button data-id="<?=$model->id?>" class="activity-update-role-power btn btn-warning btn-xs">แก้ไข</button> -->
                                <a href="<?= Url::to(['/bsdr/del','id'=>$model->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-flat">ลบ</a>
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

            
        $(".activity-update").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/bsdr/update",
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