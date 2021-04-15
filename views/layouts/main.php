<?php
use yii\helpers\Html;
use yii\bootstrap\Modal;
/* @var $this \yii\web\View */
/* @var $content string */



dmstr\web\AdminLteAsset::register($this);
    if (class_exists('backend\assets\AppAsset')) {
        backend\assets\AppAsset::register($this);
    } else {
        app\assets\AppAsset::register($this);
    }

    

    $directoryAsset = Yii::$app->assetManager->getPublishedUrl('@vendor/almasaeed2010/adminlte/dist');
    ?>
    <?php $this->beginPage() ?>
    <!DOCTYPE html>
    <html lang="<?= Yii::$app->language ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>
    </head>
    <body class="hold-transition skin-blue sidebar-mini">
    <?php $this->beginBody() ?>
    <div class="wrapper">

        <?= $this->render(
            'header.php',
            ['directoryAsset' => $directoryAsset]
        ) ?>

        <?= $this->render(
            'left.php',
            ['directoryAsset' => $directoryAsset]
        )
        ?>

        <?= $this->render(
            'content.php',
            ['content' => $content, 'directoryAsset' => $directoryAsset]
        ) ?>

    </div>
    <?php Modal::begin([
  'id' => 'activity-modal',
  'header' => '<h4 class="modal-title">.</h4>',
  'size'=>'modal-lg',
  // 'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">ปิด</a>',
  'clientOptions' => [
      'backdrop' => false, 
      'keyboard' => true
      ]
  ]);
  Modal::end();
  ?>
    <?php $this->endBody() ?>
    </body>
    </html>
    <?php $this->endPage() ?>