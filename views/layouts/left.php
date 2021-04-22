<?php
use app\models\RolePower;
$menu = [];
if(!(Yii::$app->user->isGuest)){
    $models = RolePower::find()->where(['user_id'=>Yii::$app->user->id])->all();
    foreach($models as $model){
        if($model->role_name_id == 1){
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']],);                   
            array_push($menu,['label' => 'ระบบเอกสาร', 'icon' => 'file-code-o', 'url' => ['/docz/index']],);  
            array_push($menu,['label' => 'อยู่ระหว่างดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/docz/index_2']],); 
            array_push($menu,['label' => 'ดำเนินการเสร็จ', 'icon' => 'file-code-o', 'url' => ['/docz/index_3']],);             
        }
        if($model->role_name_id == 2){
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']],);                   
            array_push($menu,['label' => 'รอดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]],);  
            // array_push($menu,['label' => 'อยู่ระหว่างดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/docz/index_2']],); 
            // array_push($menu,['label' => 'ดำเนินการเสร็จ', 'icon' => 'file-code-o', 'url' => ['/docz/index_3']],);             
        }
        if($model->role_name_id == 8){
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']],);                   
            array_push($menu,['label' => 'รอดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]],);  
            // array_push($menu,['label' => 'อยู่ระหว่างดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/docz/index_2']],); 
            // array_push($menu,['label' => 'ดำเนินการเสร็จ', 'icon' => 'file-code-o', 'url' => ['/docz/index_3']],);             
        }
        if($model->role_name_id == 9){
            array_push($menu,['label' => $model->role_name(), 'options' => ['class' => 'header text-center']],);                   
            array_push($menu,['label' => 'รอดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/doczm/index','role_name_id'=>$model->role_name_id]],);  
            // array_push($menu,['label' => 'อยู่ระหว่างดำเนินการ', 'icon' => 'file-code-o', 'url' => ['/docz/index_2']],); 
            // array_push($menu,['label' => 'ดำเนินการเสร็จ', 'icon' => 'file-code-o', 'url' => ['/docz/index_3']],);             
        }        
    }
    if(Yii::$app->user->id == 1){
        array_push($menu,['label' => 'ผู้ดูแลระบบ', 'options' => ['class' => 'header text-center']],); 
        array_push($menu,[
            'label' => 'Admin',
            'url' => '#',
            'visible' => !Yii::$app->user->isGuest && (Yii::$app->user->id == 1),
            'items' =>[
                ['label' => 'User', 'icon' => 'file-code-o', 'url' => ['/admin/user'],],                            
                ['label' => 'กำหนดสิทธ์', 'icon' => 'file-code-o', 'url' => ['/role/index'],],
                ['label' => 'กำหนดขั้นตอน', 'icon' => 'file-code-o', 'url' => ['/docprofile/index'],],
            ]
        ],); 
    }
}

?>

<aside class="main-sidebar">
    <section class="sidebar">
        <?= dmstr\widgets\Menu::widget(
            [
                'options' => ['class' => 'sidebar-menu tree', 'data-widget'=> 'tree'],
                'items' => $menu,                
            ]
        ) ?>

    </section>

</aside>
