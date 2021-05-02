<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '.';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Docz</h3>
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
                            <th style="width: 100px">วันที่</th>
                            <th >ชื่อ</th>
                            <th style="width: 50px">ไฟล์หนังสือ</th>
                            <th style="width: 50px">ไฟล์แนบ</th>
                            <th style="width: 125px"></th>
                            <th style="width: 100px"></th>
                        </tr>
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?= $model->r_number?></td>
                            <td><?= date("Y-m-d", strtotime("$model->doc_date"));?></td>
                            <td class="mailbox-subject">
                                <p> 
                                    <?= $model->doc_speed ?>
                                    <?=$model->doc_form_number ? 'ที่ '.$model->doc_form_number : ''?>
                                    <?=$model->doc_date ? 'ลงวันที่ '.date("Y-m-d",strtotime($model->doc_date)) : ''?>
                                    <?=$model->name ? 'เรื่อง '.$model->name : ''?>
                                </p>
                                <!-- <button data-id="<?=$model->id?>" class="activity-view btn btn-success btn-flat">view</button> -->
                                <!-- <?= Html::a('สถานะ', ['view_st','id'=>$model->id], ['class' => 'btn btn-success btn-flat']) ?> -->
                            </td>
                            
                            <td>   
                                <?php if($model->file){?>
                                    <a href="#" data-id="<?=$model->id?>" class="activity-view "><i class="fa fa-file-pdf-o " aria-hidden="true"></i></a>
                                <?php }else{
                                    echo 'ไม่มีหนังสือ';
                                } ?>
                            </td>
                            <td>
                                <?php foreach($model->doc_file as $f){ ?>
                                    <?php if($f->file){?>
                                        <a href="#" data-id="<?=$f->id?>" class="activity-view_att"><i class="fa fa-file-pdf-o " aria-hidden="true"></i></a>
                                    <?php } ?>
                                <?php } ?>
                                <button data-id="<?=$model->id?>" class="activity-att btn btn-warning btn-flat btn-xs">ไฟล์แนบ</button>
                            </td>
                            <td>
                                
                                
                                <button data-id="<?=$model->id?>" class="activity-update btn btn-warning btn-flat btn-md">แก้ไข</button>
                                <!-- <button data-id="<?=$model->id?>" class="activity-update-role-power btn btn-warning btn-xs">แก้ไข</button> -->
                                <a href="<?= Url::to(['/docz/del','id'=>$model->id]) ?>" onclick="return confirm('Are you sure you want to Delete ?');" class="btn btn-danger btn-flat btn-md">ลบ</a>
                            </td>
                            <td>
                            <a href="<?=Url::to(['/docz/send','id'=>$model->id])?>" class="btn btn-primary btn-block btn-flat ">เสนอ</a>
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
                "?r=docz/create",
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
                "?r=docz/view",
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
        $(".activity-view_att").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=docz/view_att",
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
        $(".activity-att").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=docz/att",
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
                "?r=docz/update",
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

        $(".activity-send").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/docz/send",
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