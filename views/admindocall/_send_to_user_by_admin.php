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
         
$fieldOption = [
    'options' => ['class' => 'checkbox has-feedback col-md-12'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];

?>
<div class="row">
  <div class="col-md-8">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><?=$Docz->name_doc()?></h3>
      </div>
      <div class="box-body text-center"> 
        <embed src="<?= Url::to('@web/'.$Docz->file) ?>" type="application/pdf" width="100%" height="800px" />
      </div>
      <?=$Docz->file?>   
    </div>
    <?php foreach($Docz->doc_file as $df){ ?>
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">ไฟล์แนบ<?=$df->name?></h3>
            </div>
            <div class="box-body text-center"> 
              <embed src="<?= Url::to('@web/'.$df->file) ?>" type="application/pdf" width="100%" height="600px" />
            </div>  
            <?=$df->file?>         
        </div>  
        <?php } ?>
  </div>
  <div class="col-md-4">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title " id="<?=$Docz->id?>"><a href="<?=Url::to(['index'])?>" class="btn btn-warning">กลับ</a></h3>
        </div>
          <div class="box-body text-center"> 
            <table class="table table-hover table-striped">
              <tr>
                <td></td>
                <td class="text-left"></td>
                <td colspan="2"><a href="<?=Url::to(['send_to_user_by_admin_all','doc_id'=>$Docz->id])?>" class="btn btn-danger" >ส่งทั้งหมด</a></td>
              </tr>
              <?php foreach($MUser as $MU){?>
              <tr>
                <td ><?= $MU['checked'] ? '<i class="fa fa-check"></i>': '' ?>
                <!-- <input disabled type="checkbox" <?= $MU['checked']?> class="checkboxA" name="check[]" value="<?=$MU['id']?>"> </td> -->
                <td class="text-left"> <?= $MU['name']?></td>
                <td ><a href="<?=Url::to(['send_to_user_by_admin_del_singer','doc_id'=>$Docz->id,'user_id'=>$MU['id']])?>" class="btn btn-warning">ดึงกลับ</a></td>
                <td ><a href="<?=Url::to(['send_to_user_by_admin_singer','doc_id'=>$Docz->id,'user_id'=>$MU['id']])?>" class="btn btn-danger">ส่ง</a></td>
              </tr>
            <?php } ?>
          </table>                 
        </div>
      </div>
      <div class="box box-primary">
        
        <div class="box-body text-center"> 
          <?php $form = ActiveForm::begin(); ?>    
            <label>ที่เก็บ</label>
            <select class="form-control" name="select[]"  multiple="multiple">
              <?php foreach($select_list  as $DocCat){?>
                <option <?=$DocCat['selected']?> value="<?=$DocCat['id']?>"><?=$DocCat['name']?></option>
              <?php } ?>
            </select>
        </div>
        <div class="box-footer"> 
          <button type="submit" class="btn btn-info pull-right">บันทึก</button>
        </div>   
          <?php ActiveForm::end(); ?>     
      </div>
      <a href="<?=Url::to(['index'])?>" class="btn btn-warning">กลับ</a>
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
