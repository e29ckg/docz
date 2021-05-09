<?php
use app\models\RolePower;
use app\models\Docz;
use app\models\DocManage;
use app\models\DocUserRead;
use app\models\DocCat;
$doc_out = DocCat::doc_out_count();
$menu = [];
if(!(Yii::$app->user->isGuest)){
    $models = RolePower::find()->select('role_name_id')->where(['user_id'=>Yii::$app->user->id])->orderBy(['role_name_id' => SORT_ASC])->all();
    foreach($models as $model){
        if($model->role_name_id == 1){
            $docz_index = Docz::find()->select('id')->where(['st'=>1])->count();
            if($docz_index == 0){$docz_index = '';}
            $docz_index_2 = Docz::find()->select('id')->where(['st'=>[2,3]])->count();
            if($docz_index_2 == 0){$docz_index_2 = '';}
            // $docz_index_3 = Docz::find()->where(['st'=>3])->count();
            // if($docz_index_3 == 0){$docz_index_3 = '';}
            // $docz_index_4 = Docz::find()->where(['st'=>4])->count();
            // if($docz_index_4 == 0){$docz_index_4 = '';}
            // $docz_index_4 = '';
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']]); 
            array_push($menu,['label' => 'ระบบเอกสาร<span id="docz_index" class="label label-danger pull-right">'.$docz_index.'</span>', 'icon' => 'file-code-o', 'url' => ['/docz/index']]);  
            array_push($menu,['label' => 'อยู่ระหว่างดำเนินการ<span id="docz_index" class="label label-warning pull-right">'.$docz_index_2.'</span>', 'icon' => 'file-code-o', 'url' => ['/docz/index_2']]); 
            // array_push($menu,['label' => 'ดำเนินการเสร็จ<span id="docz_index" class="label label-primary pull-right">'.$docz_index_3.'</span>', 'icon' => 'file-code-o', 'url' => ['/docz/index_3']]);             
            array_push($menu,['label' => 'หนังสือนอกแฟ้ม<span id="docz_index" class="label label-primary pull-right">'.$doc_out.'</span>', 'icon' => 'file-code-o', 'url' => ['/doccat/index_out']]);             
            array_push($menu,['label' => 'แฟ้มเอกสาร<span id="docz_index" class="label label-primary pull-right"></span>', 'icon' => 'file-code-o', 'url' => ['/doccat/index']]);             
        }
        if($model->role_name_id <> 1){
            $doczm_index = DocManage::find()->where(['st'=>2,'role_name_id'=>$model->role_name_id])->count();
            if($doczm_index == 0){$doczm_index = '';}
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']]);                   
            array_push($menu,['label' => 'รอดำเนินการ<span id="docz_index" class="label label-danger pull-right">'.$doczm_index.'</span>', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]]);  
                       
        }
        // if($model->role_name_id == 8){
        //     $doczm_index = DocManage::find()->where(['st'=>2,'role_name_id'=>$model->role_name_id])->count();
        //     if($doczm_index == 0){$doczm_index = '';}
        //     array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']]);                   
        //     array_push($menu,['label' => 'รอดำเนินการ<span id="docz_index" class="label label-danger pull-right">'.$doczm_index.'</span>', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]]);  
                         
        // }
        // if($model->role_name_id == 9){
        //     $doczm_index = DocManage::find()->where(['st'=>2,'role_name_id'=>$model->role_name_id])->count();
        //     if($doczm_index == 0){$doczm_index = '';}
        //     array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']]);                   
        //     array_push($menu,['label' => 'รอดำเนินการ<span id="docz_index" class="label label-danger pull-right">'.$doczm_index.'</span>', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]]);  
                        
        // }
        if($model->role_name_id == 1){
            array_push($menu,['label' => 'ผู้ดูแลระบบ', 'options' => ['class' => 'header text-center']]); 
            array_push($menu,[
                'label' => 'Admin',
                'url' => '#',
                'visible' => !Yii::$app->user->isGuest && ($model->role_name_id == 1),
                'items' =>[
                    ['label' => 'User', 'icon' => 'file-code-o', 'url' => ['/admin/user'],],                            
                    ['label' => 'กำหนดสิทธ์', 'icon' => 'file-code-o', 'url' => ['/role/index'],],
                    ['label' => 'กำหนดขั้นตอน', 'icon' => 'file-code-o', 'url' => ['/docprofile/index'],],
                    ['label' => 'กำหนดแฟ้มเอกสาร', 'icon' => 'file-code-o', 'url' => ['/doccatname/index'],],
                ]
            ]); 
        }          
    }

    $docz_index_to_read = DocUserRead::find()->where(['check'=>0,'user_id'=>Yii::$app->user->id])->count();  
    if($docz_index_to_read == 0){$docz_index_to_read = '';}
        array_push($menu,['label' => 'หนังสือของฉัน', 'options' => ['class' => 'header text-center']]);                   
        array_push($menu,['label' => 'รอการอ่าน<span id="docz_index" class="label label-danger pull-right">'.$docz_index_to_read.'</span>', 'icon' => 'file-code-o', 'url' => ['/docz/index_to_read']]);  
        array_push($menu,['label' => 'หนังสือทั้งหมด', 'icon' => 'file-code-o', 'url' => ['/docz/all']]);
}

?>

<aside class="main-sidebar">
    <section class="sidebar">
        <?= dmstr\widgets\Menu::widget(
            [
                'encodeLabels' => false,
                'options' => ['class' => 'sidebar-menu tree', 'data-widget'=> 'tree'],
                'items' => $menu,                
            ]
        ) ?>

    </section>

</aside>

