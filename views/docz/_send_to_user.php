<?php

use yii\helpers\Html;
// use yii\widgets\ActiveForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Url;
use app\models\User;
use yii\helpers\ArrayHelper;
/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
// var_dump($model->docps);

$User = User::find()->where(['status'=>10])->all();  
 
// $listUserProfile= ArrayHelper::map($User,'id','name');
$listUserProfile= ArrayHelper::map($User,'id',function ($User) {
        return $User->name;
    });
        
$fieldOption = [
    'options' => ['class' => 'checkbox has-feedback col-md-12'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];

var_dump($listUserProfile);

?>
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><?=$model->id?></h3>
            </div>
            <div class="box-body text-center"> 
                <?php $form = ActiveForm::begin([
                        'id' => 'bsdr-form',
                        'enableAjaxValidation' => true,
                        'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
                    ]
                    ); ?>
                
                        
                
                <div class="form-group">

                  <div class="checkbox">
                    <label>
                      <input type="checkbox" class="checkbox">
                      Checkbox 1
                    </label>
                  </div>
                  <?php foreach($MUser as $MU){?>
                    <div class="checkbox">
                        <label>
                            <!-- <input type="checkbox" name="DocUserRead[user_id][]" value="1"> -->
                            <input type="checkbox" class="checkbox" name="DocUserRead[user_id][]" value="<?=$MU->id?>">
                            <?=$MU->name?>
                        </label>
                    </div>
                    <?php } ?>
                </div>
        <label for="checkAll">
            <input id='checkall' type="checkbox" name="chkAll" id="chkAll">CheckAll
        </label>          
	  
  </table>   
            </div>    
            <div class="box-footer">              
            
                <button type="submit" class="btn btn-info pull-right">Submit</button>
            </div>   
            <?php ActiveForm::end(); ?>     
        </div>
        
    </div>
    
</div> 


<?php $this->registerJs('

function init_click_handlers(){
    
    $(".activity-send-del").click(function(e) {
            var fID = $(this).data("id");
            // alert(fID);
            $.get(
                "/docz/send_del",
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
    
        $("#checkall").change(function(){
            var checked = $(this).is(":checked");
            if(checked){
              $(".checkbox").each(function(){
                $(this).prop("checked",true);
              });
            }else{
              $(".checkbox").each(function(){
                $(this).prop("checked",false);
              });
            }
          });
        
         // Changing state of CheckAll checkbox 
         $(".checkbox").click(function(){
        
           if($(".checkbox").length == $(".checkbox:checked").length) {
             $("#checkall").prop("checked", true);
           } else {
             $("#checkall").removeAttr("checked");
           }
       
         });
}
init_click_handlers(); //first run
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>