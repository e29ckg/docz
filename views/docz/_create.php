<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

use yii\helpers\Url;

$this->title = 'bsdr';
$listSpeed =[
    'ด่วน'=>'ด่วน',
    'ด่วนที่สุด'=>'ด่วนที่สุด',
];
$listRoleName =[
    'ผอ.'=>'ผอ.',
    'ผู้พิพากษาหัวหน้าศาล'=>'ผู้พิพากษาหัวหน้าศาล',
];
// $fieldOption = [
//     'options' => ['class' => 'form-group has-feedback'],
//     'inputTemplate' => "{input}<span class='help-block'></span>",
//     'template'=>'{label}<div class="col-sm-10 form-group has-feedback">{input}{error}</div>'
// ];
$fieldOption = [
    'options' => ['class' => 'has-feedback col-md-4'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionC2 = [
    'options' => ['class' => 'has-feedback col-md-6'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];
$fieldOptionC12 = [
    'options' => ['class' => 'has-feedback col-md-12'],
    'inputTemplate' => "{input}<span class='form-control-feedback'></span>"
];

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

// $this->registerCssFile('http://www.google.com/ss.css');   
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
        <div class="box-body row">        
        <div class="col-md-12">
        <?= $form->field($model, 'r_number',$fieldOptionC2)
                ->textInput(['placeholder' => 'ตัวอย่าง. 1/64','maxlength' => true])
                ->label($model->getAttributeLabel('r_number')) ?>
        
        
        <?= $form->field($model, 'r_date',$fieldOption)
            ->textInput([
                'placeholder' => $model->getAttributeLabel('r_date'),
                // 'type'=>'datetime-local',
                'maxlength' => true,
                'id' => 'datepicker1'
                ])
            ->label();  ?>
        
        </div>
        
        <?= $form->field($model, 'doc_speed',$fieldOption)
                ->dropDownList(
                    $listSpeed,
                    ['prompt'=>'เลือก..']
                    )
                ->label($model->getAttributeLabel('doc_speed')) 
            ?> 
        
        <?= $form->field($model, 'doc_form_number',$fieldOption)
                ->textInput(['placeholder' => $model->getAttributeLabel('doc_form_number'),'maxlength' => true])
                ->label() ?>
        
        <?= $form->field($model, 'doc_date',$fieldOption)
            ->textInput([
                'placeholder' => $model->getAttributeLabel('doc_date'),
                'maxlength' => true,
                'id' => 'datepicker2'])
            ->label();  ?>
       
        <?= $form->field($model, 'doc_to',$fieldOptionC12)
                ->dropDownList(
                    $listRoleName,
                    ['prompt'=>'เลือก..']
                    )
                ->label($model->getAttributeLabel('doc_to')) 
            ?> 
        <?= $form->field($model, 'name',$fieldOptionC12)
                ->textInput(['placeholder' => $model->getAttributeLabel('name'),'maxlength' => true])
                ->label($model->getAttributeLabel('name')) ?>
        
        
        
           
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
    // $( "#datepicker2" ).datepicker();
    $("#datepicker1").datetimepicker({
        // format: "yyyy-mm-dd",
      });

    $("#datepicker2").datepicker({
        format: "yyyy-mm-dd",
        todayBtn: true,
        language:"th-th",             
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