<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css?v=1.44',
        'css/jquery.datetimepicker.css',
        'css/dataTables.bootstrap.css',
        'css/select2.min.css',
    ];
    public $js = [
        'js/jquery.datetimepicker.js',
        'js/jquery.dataTables.min.js',
        'js/dataTables.bootstrap.js',
        'js/select2.min.js'
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}
