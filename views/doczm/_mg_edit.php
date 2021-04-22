<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;

$this->title = 'mg_edit';

$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
?>
          <!-- Horizontal Form -->
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title"></h3>
        </div>
            <!-- /.box-header -->
            <!-- form start -->
        <!-- <form class="form-horizontal"> -->
        <?php $form = ActiveForm::begin([
            'id' => 'mg_edit-form',
            'enableAjaxValidation' => true,
            'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
        ]
        ); ?>
        <div class="box-body">        

            <?= $form->field($model, 'detail',$fieldOption)
                ->textarea(['rows' => '6','placeholder' => $model->getAttributeLabel('detail'),'maxlength' => true])
                ->label($model->getAttributeLabel('detail'),['class'=>'col-sm-2 control-label']) ?>
                    
            <!-- /.box-body -->
            <div class="box-footer">              
            
                <button type="submit" class="btn btn-info pull-right">Save</button>
            </div>
            <!-- /.box-footer -->
        <!-- </form> -->
        <?php ActiveForm::end(); ?>
    </div>
          <!-- /.box -->    

          
<?php $this->registerJs('

function init_click_handlers(){
    // $( "#datepicker2" ).datepicker({ defaultDate: "04/19/2021" });
    $("#datepicker2").datepicker({
        format: "yyyy-mm-dd",
        todayBtn: true,
        language: "th",             
        thaiyear: true ,
      }).datepicker("setDate", "0");

    $("#activity-changepassword").click(function(e) {
      var fID = $(this).data("id");
      // alert(fID);
        $.get(
            "/profile/change_password",{id:fID},
            function (data)
            {
                $("#activity-modal").find(".modal-body").html(data);
                $(".modal-body").html(data);
                $(".modal-title").html("แก้ไขรหัสผ่าน");
                $("#activity-modal").modal("show");
            }
        );
    });
    $("#activity-update-profile").click(function(e) {
      var fID = $(this).data("id");
      // alert(fID);
        $.get(
            "/profile/update_profile",{id:fID},
            function (data)
            {
                $("#activity-modal").find(".modal-body").html(data);
                $(".modal-body").html(data);
                $(".modal-title").html("แก้ไขข้อมูล");
                $("#activity-modal").modal("show");
            }
        );
    });
    $("#activity-update-profile2").click(function(e) {
      var fID = $(this).data("id");
      // alert(fID);
        $.get(
            "/profile/update_profile",{id:fID},
            function (data)
            {
                $("#activity-modal").find(".modal-body").html(data);
                $(".modal-body").html(data);
                $(".modal-title").html("แก้ไขข้อมูล");
                $("#activity-modal").modal("show");
            }
        );
    });
    $("#activity-role-create").click(function(e) {
      var fID = $(this).data("id");
      // alert(fID);
        $.get(
            "/profile/role_create",{id:fID},
            function (data)
            {
                $("#activity-modal").find(".modal-body").html(data);
                $(".modal-body").html(data);
                $(".modal-title").html("แก้ไขข้อมูล");
                $("#activity-modal").modal("show");
            }
        );
    });

    $(".activity-role-update").click(function(e) {
      var fID = $(this).data("id");
        $.get(
            "/profile/role_update",{id:fID},
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
//   init_click_handlers(); //reactivate links in grid after pjax update
// });

');?>