<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;

$this->title = $model->profile->name;
$this->params['breadcrumbs'][] = ['label' => 'Profiles', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$dataRole = ['ddd','aaaa','dddd'];
?>
<div class="row">
        <div class="col-md-3">
          <!-- Profile Image -->
          <div class="box box-primary">
            <div class="box-body box-profile">
              <img class="profile-user-img img-responsive img-circle" 
                src="<?= $model->profile->photo ? Url::to('@web/img/photo/user2-160x160.jpg') : Url::to('@web/img/user2-160x160.jpg')?>" alt="User profile picture">

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
              <h3 class="box-title">About Me</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">  

              <strong><i class="fa fa-pencil margin-r-5"></i> Role</strong>

              <p>
                <?php
                    foreach($dataRole as $role){?>
                        <span class="label label-danger"><?= $role ?></span>
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
                  <!-- <div class="form-group">
                    <label for="inputName" class="col-sm-2 control-label">Name</label>

                    <div class="col-sm-10">
                      <input type="text" class="form-control" id="inputName" placeholder="Name">
                    </div>
                  </div> -->
                  <!-- <div class="form-group">
                    <label for="inputExperience" class="col-sm-2 control-label">Experience</label>

                    <div class="col-sm-10">
                      <textarea class="form-control" id="inputExperience" placeholder="Experience"></textarea>
                    </div>
                  </div> -->
                  <!-- <div class="form-group">
                    <label for="inputSkills" class="col-sm-2 control-label">Skills</label>

                    <div class="col-sm-10">
                      <input type="text" class="form-control" id="inputSkills" placeholder="Skills">
                    </div>
                  </div> -->
                  <!-- <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                      <div class="checkbox">
                        <label>
                          <input type="checkbox"> I agree to the <a href="#">terms and conditions</a>
                        </label>
                      </div>
                    </div>
                  </div> -->
                  <div class="form-group">
                    <!-- <div class="col-sm-offset-2 col-sm-2">
                      <button type="submit" class="btn btn-danger">Submit</button>
                    </div> -->
                    <div class="col-sm-offset-2 col-sm-2">
                      <!-- <button type="button"  id="activity-changepassword" data-id="<?=$model->id?>" data-target = "activity-modal" class="btn btn-warning" >Change Password</button> -->
                      
                    </div>
                  </div>
                </form>
              </div>
              <!-- /.tab-pane -->
            </div>
            <!-- /.tab-content -->
          </div>
          <!-- /.nav-tabs-custom -->
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
            "/admin/update_profile",{id:fID},
            function (data)
            {
                $("#activity-modal").find(".modal-body").html(data);
                $(".modal-body").html(data);
                $(".modal-title").html("แก้ไขข้อมูล");
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


