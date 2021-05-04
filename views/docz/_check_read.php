<?php

use yii\helpers\Html;
// use yii\widgets\ActiveForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Url;
use app\models\User;
use yii\helpers\ArrayHelper;

?>
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><?=$model->name_doc()?></h3>
            </div>
            <div class="box-body text-center"> 
                <ul class="timeline">
                  <?php foreach($model->doc_user_read as $DUR){?>
                  <!-- timeline item -->
                  <li>
                      <!-- timeline icon -->
                      <?=$DUR->check == 1 ? '<i class="fa fa-check bg-blue"></i>' :'<i class="fa fa-close bg-red"></i>'?>
                      
                      <div class="timeline-item">
                          <span class="time"><i class="fa fa-clock-o"></i><?=$DUR->updated?></span>
                          <h3 class="timeline-header text-left"><a href="#"><?=$DUR->username()?></a> </h3>
                      </div>
                  </li>
                  <?php } ?>
                </ul>     

            </div>
	     
            <div class="box-footer">              
            
            </div>   
             
        </div>
        
    </div>
    
</div> 



