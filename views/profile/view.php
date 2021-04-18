<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;

/* @var $this yii\web\View */
/* @var $model app\models\UserProfile */

$this->title = $model->name;
// $this->params['breadcrumbs'][] = ['label' => 'Profiles', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$dataRole = ['ddd','aaaa','dddd'];
?>
<div class="row">
  <div class="col-md-3">

    <!-- Profile Image -->
    <div class="box box-primary">
      <div class="box-body box-profile">
        <img class="profile-user-img img-responsive img-circle" 
          src="<?= $model->profile->image($model->profile->photo)?>" alt="User profile picture">

        <h3 class="profile-username text-center"><?= $model->profile->pfname.$model->profile->name . ' ' .$model->profile->sname?></h3>

        <p class="text-muted text-center"><?= $model->profile->dep_name?><br>
        <?=$model->profile->group_work?></p>

        <ul class="list-group list-group-unbordered">
          <li class="list-group-item">
            <b>Phone</b> <a class="pull-right"><?=$model->profile->phone?></a>
          </li>
          <li class="list-group-item">
            <b>Line ID</b> <a class="pull-right"><?=$model->profile->line_id?></a>
          </li>                
        </ul>

        <!-- <a href="#" class="btn btn-primary btn-block"><b>Follow</b></a> -->
        <button type="button"  id="activity-update-profile" data-id="<?=$model->id?>" data-target = "activity-modal" class="btn btn-primary btn-block" >แก้ไขข้อมูล</button>
                
      </div>
      <!-- /.box-body -->
    </div>
    <!-- /.box -->

    <!-- About Me Box -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><strong><i class="fa fa-pencil margin-r-5"></i> Role Power</strong></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">  

        <!-- <strong><i class="fa fa-pencil margin-r-5"></i> Role Power</strong> -->

        <!-- <p> -->
          <?php
              foreach($model->rolepower as $role){?>
                  <span class="label label-danger"><?= $role->role_name->name ?></span>
          <?php } ?>
          <!-- <span class="label label-danger">UI Design</span>
          <span class="label label-success">Coding</span>
          <span class="label label-info">Javascript</span>
          <span class="label label-warning">PHP</span>
          <span class="label label-primary">Node.js</span> -->
        </p>

      </div>
      <!-- /.box-body -->
    </div>
    <!-- /.box -->
  </div>
  <!-- /.col -->
  <div class="col-md-9">
    <div class="nav-tabs-custom">
      <ul class="nav nav-tabs">
        <li class="active"><a href="#settings" data-toggle="tab">settings</a></li>
      </ul>
      <div class="tab-content">
        
        <div class="active tab-pane" id="settings">
        
          <form class="form-horizontal">
            <div class="form-group">
              <label for="inputName" class="col-sm-2 control-label">UserName</label>
              <div class="col-sm-10">
                <input type="name" class="form-control" id="inputName" placeholder="Name" value="<?=$model->username?>" disabled="">
              </div>
            </div>

            <div class="form-group">
              <label for="inputEmail" class="col-sm-2 control-label">Email</label>

              <div class="col-sm-10">
                <input type="email" class="form-control" id="inputEmail" placeholder="Email" value="<?=$model->email?>" disabled="">
              </div>
            </div>
            
            <div class="form-group">
              <!-- <div class="col-sm-offset-2 col-sm-2">
                <button type="submit" class="btn btn-danger">Submit</button>
              </div> -->
              <div class="col-sm-offset-2 col-sm-2">
                <button type="button"  id="activity-changepassword" data-id="<?=$model->id?>" data-target = "activity-modal" class="btn btn-warning" >Change Password</button>
                
              </div>
            </div>
          </form>
        </div>
        <!-- /.tab-pane -->
      </div>
      <!-- /.tab-content -->
    </div>
    <!-- /.nav-tabs-custom -->
    <div class="row">
                
      <div class="col-md-4">
        <!-- Widget: user widget style 1 -->
        <div class="box box-widget widget-user-2">
          <!-- Add the bg color to the header using any of the bg-* classes -->
          <div class="widget-user-header bg-red">
            <div class="widget-user-image">
              <img class="img-circle" src="<?=Url::to('@web/img/crown.png')?>" alt="User Avatar">
            </div>
            <!-- /.widget-user-image -->
            <h3 class="widget-user-username">ตำแหน่งปกติ</h3>
            <h5 class="widget-user-desc">ตำแหน่ง</h5>
          </div>
          <div class="box-footer no-padding">
            <ul class="nav nav-stacked">
              <li><a href="#" class="text-center"><?= $model->profile->dep_name ? $model->profile->dep_name:'-'?></a></li>
              <!-- <li><a href="#">Tasks <span class="pull-right badge bg-aqua">5</span></a></li>
              <li><a href="#">Completed Projects <span class="pull-right badge bg-green">12</span></a></li> -->
              <li>
                <button type="button"  id="activity-update-profile2" data-id="<?=$model->id?>" data-target = "activity-modal" class="btn btn-primary btn-block" >แก้ไขข้อมูล</button>
                <!-- <a href="#" class="text-center btn btn-warning btn-block">แก้ไข</a> -->
              </li>
            </ul>
          </div>
        </div>
        <!-- /.widget-user -->
      </div>

<?php foreach($model->role as $role){ ?>
        <div class="col-md-4">
          <!-- Widget: user widget style 1 -->
          <div class="box box-widget widget-user">
            <!-- Add the bg color to the header using any of the bg-* classes -->
            <div class="widget-user-header bg-aqua-active">
              <h3 class="widget-user-username"><?=$role->role_name->name?></h3>
              <h5 class="widget-user-desc">ตำแหน่งใช้ลงนาม</h5>
            </div>
            
            <div class="box-footer no-padding">
            <ul class="nav nav-stacked">
              <li><h5 class="text-center"><?= $role->name_dep1 ? $role->name_dep1 : '-'?></h5></li>
              <li><h5 class="text-center"><?= $role->name_dep2 ? $role->name_dep2 : '-'?></h5></li>
              <li><h5 class="text-center"><?= $role->name_dep3 ? $role->name_dep3 : '-'?></h5></li>
              <li>
              <button data-id="<?=$role->id?>" class="activity-role-update btn btn-warning btn-block" data-target = "activity-modal">แก้ไข</button>
              <!-- <a href="<?=Url::to(['/profile/role_update','id'=>$role->id])?>">แก้ไข</a> -->
              <!-- <a href="<?=Url::to(['/profile/role_del','id'=>$role->id])?>" class="pull-right">ลบ</a> -->
              </li>
            </ul>
          </div>
          </div>
          <!-- /.widget-user -->
        </div>

      <!-- <div class="col-md-4 -->
        <!-- /.widget-user -->
      <!-- </div> -->
<?php } ?>
      

      <div class="col-md-4">
        <!-- Widget: user widget style 1 -->
        <div class="box box-widget widget-user-2">
          <!-- Add the bg color to the header using any of the bg-* classes -->
          <div class="widget-user-header bg-red">
            <div class="widget-user-image">
              <img class="img-circle" src="<?=Url::to('@web/img/crown.png')?>" alt="User Avatar">
            </div>
            <!-- /.widget-user-image -->
            <h3 class="widget-user-username">เพิ่ม</h3>
            <!-- <h5 class="widget-user-desc">Lead Developer</h5> -->
          </div>
          <div class="box-footer no-padding">
            <ul class="nav nav-stacked">
              <li>
                <button type="button"  id="activity-role-create" data-id="<?=$model->id?>" data-target = "activity-modal" class="btn btn-primary btn-block" >เพิ่ม</button>
                <!-- <a href="<?=Url::to(['/profile/role_create','id'=>$model->id])?>" class="text-center">เพิ่ม</a>  -->
              </li>
              <!-- <li><a href="#">Tasks <span class="pull-right badge bg-aqua">5</span></a></li>
              <li><a href="#">Completed Projects <span class="pull-right badge bg-green">12</span></a></li>
              <li><a href="#">Followers <span class="pull-right badge bg-red">842</span></a></li> -->
            </ul>
          </div>
        </div>
        <!-- /.widget-user -->
      </div>

    </div>
  </div>
  <!-- /.col -->
</div>
      
<?php 
// Modal::begin([
//   'id' => 'activity-modal',
//   'header' => '<h4 class="modal-title">.</h4>',
//   'size'=>'modal-md',
//   // 'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">ปิด</a>',
//   'clientOptions' => [
//       'backdrop' => false, 
//       'keyboard' => true
//       ]
//   ]);
//   Modal::end();
  ?>

<?php $this->registerJs('

function init_click_handlers(){
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


