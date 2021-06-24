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
                <table class="table table-bordered" id="example2">
                    <thead>
                        <tr>
                            <th style="width: 50px">#</th>
                            <th >ชื่อ</th>
                            <th style="width: 50px"></th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?= $model->r_number?></td>
                            <td>
                                <?=$model->name_doc()?>
                            </td>       
                            <td>
                                <a href="#" data-id="<?=$model->id?>" class="activity-ckeck-read btn btn-success btn-block btn-flat ">ตรวจสอบการอ่าน</a>
                            </td>
                            <td>
                            <a href="<?=Url::to(['/docz/to_read','id'=>$model->id])?>" class="btn btn-primary btn-block btn-flat ">อ่าน</a>
                            
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
        
    $(".activity-ckeck-read").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=docz/check_read",
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
$("#example2").DataTable();
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>