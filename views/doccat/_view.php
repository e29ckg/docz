<?php

use yii\helpers\Html;
// use yii\widgets\ActiveForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Url;
use app\models\User;
use app\models\Doccatname;
use yii\helpers\ArrayHelper;
/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */
/* @var $form yii\widgets\ActiveForm */
 
 

// $listDocCat = ArrayHelper::map($DocCat,'id','name');
        
$fieldOption = [
    'options' => ['class' => 'checkbox has-feedback col-md-12'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];

// var_dump($DocCats);

?>
<div class="row">
  <div class="col-md-8">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><?=$model->name_doc()?></h3>
      </div>
      <div class="box-body text-center"> 
        <embed src="<?= Url::to('@web/'.$model->file) ?>" type="application/pdf" width="100%" height="800px" />
      </div>
    </div>
    <?php foreach($model->doc_file as $df){ ?>
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">ไฟล์แนบ<?=$df->name?></h3>
            </div>
            <div class="box-body text-center"> 
              <embed src="<?= Url::to(['@web/'.$df->file]) ?>" type="application/pdf" width="100%" height="600px" />
            </div>            
        </div>  
        <?php } ?>
  </div>
    <div class="col-md-4">        
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">การอ่าน</h3>
            </div>
            <div class="box-body text-center"> 
                <ul class="timeline">
                  <?php foreach($model->doc_user_read as $DUR){?>
                  <!-- timeline item -->
                  <li>
                      <!-- timeline icon -->
                      <?=$DUR->check == 1 ? '<i class="fa fa-check bg-blue"></i>' :'<i class="fa fa-close bg-red"></i>'?>
                      
                      <div class="timeline-item">
                          <span class="time"><i class="fa fa-clock-o"></i><?=$DUR->updated?></span>
                          <h3 class="timeline-header text-left"><a href="#"><?=$DUR->username()?></a> </h3>
                      </div>
                  </li>
                  <?php } ?>
                </ul>     

            </div>
	     
            <div class="box-footer">              
            
            </div>   
             
        </div>
    </div>
    
</div> 


<?php $this->registerJs('

function init_click_handlers(){
        
        $("#checkall").change(function(){
            var checked = $(this).is(":checked");
            if(checked){
              $(".checkboxA").each(function(){
                $(this).prop("checked",true);
              });
            }else{
              $(".checkboxA").each(function(){
                $(this).prop("checked",false);
              });
            }
          });
        
         // Changing state of CheckAll checkbox 
         $(".checkboxA").click(function(){
        
           if($(".checkboxA").length == $(".checkboxA:checked").length) {
             $("#checkall").prop("checked", true);
           } else {
             $("#checkall").removeAttr("checked");
           }
       
         });
}
init_click_handlers(); //first run

$("select").select2({
  // data :[{"id":1,"text":"ssss"}]
});
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>
