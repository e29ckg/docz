<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'เอกสารทั้งหมด';
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
                <table class="table table-bordered" id="example2">
                    <thead>
                        <tr>
                            <th ></th>
                            <th >ชื่อ</th>
                            <th style="width: 150px">แฟ้ม</th>
                            <th style="width: 150px">เอกสารอยู่ที่</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php foreach($models as $model){ ?>
                        <tr>   
                            <td><?=date("Ymd", strtotime($model->doc_date));?></td>                                                   
                            <td>
                                <p> 
                                    <?=$model->name_doc()?>
                                </p> 
                            </td>
                            <td>                                
                                <?=$model->doc_cat_name($model->id)?>
                            </td>
                            <td>
                                <a href="<?=Url::to(['send_to_user_by_admin','id'=>$model->id])?>" class="activity-send-to-user btn btn-danger btn-block btn-flat ">จ่ายงาน/เก็บ</a>
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
    
       $(".activity-check").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=docz/check",
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
    
}
init_click_handlers(); //first run
$("#example2").DataTable({
    "order": [[ 0, "desc" ]]
} );

// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>