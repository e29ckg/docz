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

$User = User::find()->where(['status'=>10])->all();  
$DocCats = Doccatname::find()->all();  
 
// $listUserProfile= ArrayHelper::map($User,'id','name');
$listUserProfile = ArrayHelper::map($User,'id',function ($User) {
    return $User->name;
});

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
        <h3 class="box-title"><a href="<?=Url::to(Yii::$app->request->referrer)?>" class="btn btn-warning">กลับ</a> <?=$Docz->name_doc()?></h3>
      </div>
      <div class="box-body text-center"> 
        <embed src="<?= Url::to('@web/'.$Docz->file) ?>" type="application/pdf" width="100%" height="800px" />
      </div>
    </div>
    <?php foreach($Docz->doc_file as $df){ ?>
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
              <h3 class="box-title"><?=$Docz->id?></h3>
            </div>
            <div class="box-body text-center"> 
                <?php $form = ActiveForm::begin([
                        'id' => 'bsdr-form',
                        'enableAjaxValidation' => true,
                        // 'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
                    ]
                    ); ?>         
                    <table class="table table-hover table-striped">
                      <tr>
                        <td><input id='checkall' type="checkbox" name="chkAll" id="chkAll"></td>
                        <td class="text-left">ทั้งหมด</td>
                      </tr>
                 
                    <?php foreach($MUser as $MU){?>
                      <tr>
                        <td ><input type="checkbox" class="checkboxA" name="DocUserRead[user_id][]" value="<?=$MU['id']?>"> </td>
                        <td class="text-left"> <?= $MU['name']?></td>
                      </tr>
                    <?php } ?>
                </table>                 
            </div>
            <div class="col-md-8">
            <label>ที่เก็บ</label>
              <select class="form-control" name="select[]"  multiple="multiple">
              <?php foreach($DocCats  as $DocCat){?>
                <!-- <option selected="selected">orange</option>
                <option>white</option> -->
                <option value="<?=$DocCat->id?>"><?=$DocCat->name?></option>
                <?php } ?>
              </select>
                <!-- /.form-group -->
            
            </div>
              
            <div class="box-footer">              
            
                <button type="submit" class="btn btn-info pull-right">บันทึก / ส่ง</button>
            </div>   
            <?php ActiveForm::end(); ?>     
        </div>
        <a href="<?=Url::to(Yii::$app->request->referrer)?>" class="btn btn-warning">กลับ</a>
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
$("#body").addClass("sidebar-collapse");
$("select").select2({
  // data :[{"id":1,"text":"ssss"}]
});
// $("#customer_pjax_id").on("pjax:success", function() {
//     init_click_handlers(); //reactivate links in grid after pjax update
// });
');?>
