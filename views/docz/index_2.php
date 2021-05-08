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
                    
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 100px">เลขที่รับ</th>
                            <th style="width: 100px">วันที่</th>
                            <th >ชื่อ</th>
                            <th style="width: 200px"></th>
                            <th style="width: 150px">เอกสารอยู่ที่</th>
                        </tr>
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?= $model->r_number?></td>
                            <td>
                                <?= date("Y-m-d", strtotime("$model->doc_date"));?>
                            </td>                            
                            <td>
                                <p> 
                                    <?=$model->name_doc()?>
                                </p> 
                            </td>
                            <td>
                                <?php 
                                    $x=count($model->doc_manage);
                                    $i=0;
                                    foreach($model->doc_manage as $dm){
                                        if($dm->st == '3'){
                                            $i++;
                                        }
                                        if($dm->st  == '2'){
                                            $role_name = $dm->role_name();
                                        }
                                    } 
                                    $x = ($i / $x) * 100 ;  
                                    // echo (1 / 3 ) * 100;  
                                ?>
                                <div class="progress active">
                                    <div class="progress-bar progress-bar-danger progress-bar-striped" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: <?= $x==0 ? '5' : $x; ?>%">
                                        <span class="sr-only"><?=$x?>% Complete (success) </span>
                                    </div>
                                </div> 
                            </td>
                            <td>
                                <span class="pull-right-container">
                                    <?php if($x == 100 ){?>
                                        <a href="<?=Url::to(['/docz/send_to_user','id'=>$model->id])?>" class="activity-send-to-user btn btn-warning btn-block btn-flat ">จ่ายงาน/เก็บ</a>
                                    <?php }else{ ?>
                                        <small class="label pull-right bg-blue"><?= isset($role_name) ? $role_name : '-'?></small>
                                    <?php } ?>
                                    
                                </span> 
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
                "/docz/create",
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
                "/docz/view",
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
                "/docz/view_att",
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
                "/docz/att",
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
                "/docz/update",
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