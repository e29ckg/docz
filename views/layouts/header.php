<?php
use yii\helpers\Html;
use yii\helpers\Url;
use app\models\UserProfile;
use app\models\User;
/* @var $this \yii\web\View */
/* @var $content string */

if( Yii::$app->user->identity ){
    $model = UserProfile::findOne(['user_id' =>  Yii::$app->user->identity->id]);
}
?>

<header class="main-header">

    <?= Html::a('<span class="logo-mini">DZ</span><span class="logo-lg">' . Yii::$app->name . '</span>', Yii::$app->homeUrl, ['class' => 'logo']) ?>

    <nav class="navbar navbar-static-top" role="navigation">

        <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
            <span class="sr-only">Toggle navigation</span>
        </a>

        <div class="navbar-custom-menu">

            <ul class="nav navbar-nav">
            <?php 
                if(!Yii::$app->user->isGuest){?>

                <li class="dropdown user user-menu">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <!-- <img src="<?= $directoryAsset ?>/img/user2-160x160.jpg" class="user-image" alt="User Image"/> -->
                        <span class="hidden-xs"><?=  Yii::$app->user->identity ? $model->pfname.$model->name.' '.$model->sname :'Guest'?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <!-- User image -->
                        <li class="user-header">
                            <img src="<?= $model->image($model->photo)?>" class="img-circle"
                                 alt="User Image"/>

                            <p>
                                <?= Yii::$app->user->identity ? $model->pfname.$model->name.' '.$model->sname :'Guest'?>
                                <!-- Alexander Pierce - Web Developer -->
                                <small><?= Yii::$app->user->identity ? $model->dep_name:''?></small>
                            </p>
                        </li>
                        
                        <li class="user-footer">
                            <div class="pull-left">
                                <a href="<?= Url::to(['/profile/view'])?>" class="btn btn-default btn-flat">Profile</a>
                            </div>
                            <div class="pull-right">
                                <?= Html::a(
                                    'Sign out',
                                    ['/site/logout'],
                                    ['data-method' => 'post', 'class' => 'btn btn-default btn-flat']
                                ) ?>
                            </div>
                        </li>
                    </ul>
                </li>
                
            <?php }else{?>
                <li>
                    <a href="<?= Url::to(['/profile/create_profile'])?>">สมัครสมาชิก</a>
                </li>
                <li>
                    <a href="<?= Url::to(['/site/login'])?>">Login</a>
                </li>
                
                
            <?php } ?>
            
        </div>
    </nav>
</header>
