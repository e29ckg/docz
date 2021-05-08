<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'หนังสือนอกแฟ้ม';
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
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width: 10px">#</th>
                            <th >ชื่อแฟ้มเอกสาร</th>
                            <th style="width: 100px" ></th>
                            <th style="width: 100px"></th>
                        </tr>
                        <?php foreach($data as $da){ ?>
                        <tr>
                            <td><?=$da['doc_id']?></td>
                            <td><?=$da['name']?></td>
                             <td><button data-id="<?=$da['doc_id']?>" class="activity-view btn btn-primary btn-flat btn-md">view</button></td>   
                            <td>   
                                <a href="<?=Url::to(['/docz/send_to_user','id'=>$da['doc_id']])?>" class="activity-send-to-user btn btn-warning btn-block btn-flat ">จ่ายงาน/เก็บ</a>                             
                                
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
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>