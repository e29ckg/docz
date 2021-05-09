<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = $tiile;
$this->params['breadcrumbs'][] =['label' => 'แฟ้มเอกสาร', 'url' => ['index']];;
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Docz</h3>
                <div class="box-tools">
                    <!-- <?= Html::a('Create', ['create'], ['class' => 'btn btn-success btn-flat']) ?> -->
                    <!-- <button id="activity-create" class="btn btn-success btn-flat">เพิ่ม</button> -->
                </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
            <table class="table table-bordered" id="example2">
                    <thead>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th >ชื่อหนังสือ</th>
                            <th style="width: 50px"></th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php foreach($models as $model){ ?>
                        <tr>
                            <td><?=$model->id?></td>
                            <td><?=$model->docz->name_doc()?></td>
                             <td><a href="#" data-id="<?=$model->doc_id?>" class="activity-ckeck-read btn btn-success btn-block btn-flat ">ตรวจสอบการอ่าน</a>
                            </td>   
                            <td>
                                <button data-id="<?=$model->doc_id?>" class="activity-view btn btn-warning btn-flat btn-md">view</button>
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
     
   $(".activity-view").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "?r=docz/to_read",
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