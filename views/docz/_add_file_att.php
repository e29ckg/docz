<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;

$this->title = 'bsdr';

$fieldOption = [
    'options' => ['class' => 'form-group has-feedback'],
    'inputTemplate' => "{input}<span class='help-block'></span>",
    'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
];
// $fieldOption = [
//     'options' => ['class' => 'form-group has-feedback col-md-4'],
//     'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
// ];

// 'id' => 'ID',
//             'r_number' => 'R Number',
//             'r_date' => 'R Date',
//             'doc_speed' => 'Doc Speed',
//             'doc_form_number' => 'Doc Form Number',
//             'doc_date' => 'Doc Date',
//             'doc_to' => 'Doc To',
//             'name' => 'Name',
//             'file' => 'File',
//             'user_create' => 'User Create',
//             'created' => 'Created',
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
            'id' => 'bsdr-form',
            'enableAjaxValidation' => true,
            'options' => ['enctype' => 'multipart/form-data','class'=>'form-horizontal']
        ]
        ); ?>
        <div class="box-body">        

        <?= $form->field($model, 'name',$fieldOption)
                ->textInput(['placeholder' => $model->getAttributeLabel('name'),'maxlength' => true])
                ->label($model->getAttributeLabel('name'),['class'=>'col-sm-2 control-label']) ?>
        
            <?= $form->field($model, 'file',$fieldOption)->fileInput()
                ->label($model->getAttributeLabel('file'),['class'=>'col-sm-2 control-label']); ?>                                
            </div>
            <!-- /.box-body -->
            <div class="box-footer">              
            
                <button type="submit" class="btn btn-info pull-right">Submit</button>
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
                $(".modal-title").html("???????????????????????????????????????");
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
                $(".modal-title").html("?????????????????????????????????");
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
                $(".modal-title").html("?????????????????????????????????");
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
                $(".modal-title").html("?????????????????????????????????");
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