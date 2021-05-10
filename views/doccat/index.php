<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'แฟ้มเอกสาร';
$this->params['breadcrumbs'][] = $this->title;
$BG =['bg-aqua','bg-green','bg-yellow','bg-red']
?>

<div class="row">
<?php foreach($models as $model){ ?>
    <div class="col-lg-3 col-xs-6">
          <!-- small box -->
        <?php $y = $model->id % 4?>
        <div class="small-box <?=$BG[$y]?>">
        <div class="inner">
            <h3><?=$model->doc_cat_count($model->id)?></h3>
            <p><?=$model->name?></p>
        </div>
        <div class="icon">
            <i class="fa fa-files-o"></i>
        </div>
        <a href="<?= Url::to(['/doccat/index','doc_cat_name_id'=>$model->id])?>" class="small-box-footer">
            เข้าดู <i class="fa fa-arrow-circle-right"></i>
        </a>
        </div>
    </div>
<?php } ?>
    


</div>

<?php $this->registerJs('

function init_click_handlers(){
    $("#activity-create").click(function(e) {
            $.get(
                "?r=doccatname/create",
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
                "?r=doccatname/update",
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