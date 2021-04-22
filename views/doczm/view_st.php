<div class="box">
    <div class="box box-body">
    <ul class="timeline timeline-inverse">
        <li class="time-label">
            <span class="bg-red">
                10 Feb. 2014
            </span>
        </li>
        <?php foreach($model->doc_manage as $dm){ ?>
       
        <li>
            <i class="fa fa-user bg-aqua bg-blue">1</i>

            <div class="timeline-item">
                <span class="time"><i class="fa fa-clock-o"></i> 12:05</span>

                <h3 class="timeline-header"><a href="#"><?=$dm->sort.$dm->id.'. '.$dm->role_name()?></h3>

                <div class="timeline-body">

                </div>
                <div class="timeline-footer">
                <a class="btn btn-primary btn-xs">Read more</a>
                <a class="btn btn-danger btn-xs">Delete</a>
                </div>
            </div>
        </li>
        <!-- END timeline item --> 
        <?php } ?>

        
        
       
        <li>
        <i class="fa fa-clock-o bg-gray"></i>
        </li>
    </ul>
    </div>
    
</div>