<?php
use yii\helpers\Url;
use lavrentiev\widgets\toastr\Notification;
/* @var $this yii\web\View */
// Yii::$app->session->setFlash('success', ['Error 1', 'Error 2', 'Error 3']);
$this->title = 'ระบบจัดการเอกสาร';
// Notification::widget([
//     'type' => 'info',
//     'title' => 'Toast Notifications',
//     'message' => 'Simple javascript toast notifications'
// ]);
use app\models\DocUserRead;
use app\models\UserProfile;
$docz_index_to_read = DocUserRead::find()->where(['check'=>0,'user_id'=>Yii::$app->user->id])->count();
$mo = UserProfile::find()->select('line_id')->where(['user_id' => 1])->one();
// echo $mo->line_id;
// var_dump($mo);
?>

<div class="site-index">

    <div class="body-content">

        <div class="row">
            <div class="col-lg-3 col-xs-6">
            <!-- small box -->
                <div class="small-box bg-red">
                    <div class="inner">
                    <h3><?=$docz_index_to_read?></h3>

                    <p>เอกสารใหม่</p>
                    </div>
                    <div class="icon">
                    <i class="fa fa-envelope-o"></i>
                    </div>
                    <a href="<?= Url::to(['/docz/index_to_read']) ?>" class="small-box-footer">
                    อ่าน <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>                
            </div>
            <div class="col-lg-3 col-xs-6">
            <!-- small box -->
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>-</h3>
                        <p>เอกสารทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-files-o"></i>
                    </div>
                    <a href="<?= Url::to(['/docz/all']) ?>" class="small-box-footer">
                        อ่าน <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
