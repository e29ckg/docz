<?php
namespace app\assets;
use yii\web\AssetBundle;

class AdminLtePluginAsset extends AssetBundle
{
    public $sourcePath = '@vendor/almasaeed2010/adminlte';
    public $css = [
        // 'bower_components/chart.js/Chart.min.css',
        'bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css',
        // 'plugins/chart.js/Chart.min.css',
        // more plugin CSS here
    ];
    public $js = [
        // 'plugins/chart.js/Chart.bundle.min.js'
        'bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
        // more plugin Js here
    ];
    public $depends = [
        // 'web\AdminLteAsset',
    ];
}