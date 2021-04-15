<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap\Modal;

?>
<p>
            <?= Html::button('เพิ่มข้อมูลสมาชิก', 
                ['value' => Url::to(['/signup']),
                'title' => 'เพิ่มข้อมูลสมาชิก', 
                'class' => 'btn btn-success',
                'id'=>'activity-create-link',
                'data-target' => '#staticBackdrop'
                ]
                ); ?>
        </p>
        
        <?php Modal::begin([
        'id' => 'activity-modal',
        'header' => '<h4 class="modal-title">สมาชิก</h4>',
        'size'=>'modal-lg',
        'footer' => '<a href="#" class="btn btn-primary" data-dismiss="modal">ปิด</a>',
        'clientOptions' => [
            'backdrop' => false, 
            'keyboard' => true
            ]
        ]);
        Modal::end();
        ?>

<?php $this->registerJs('

        function init_click_handlers(){
            $("#activity-create-link").click(function(e) {
                    $.get(
                        "/signup",
                        function (data)
                        {
                            $("#activity-modal").find(".modal-body").html(data);
                            $(".modal-body").html(data);
                            $(".modal-title").html("เพิ่มข้อมูลสมาชิก");
                            $("#activity-modal").modal("show");
                        }
                    );
                });
            $(".activity-view-link").click(function(e) {
                    var fID = $(this).closest("tr").data("key");
                    $.get(
                        "view",
                        {
                            id: fID
                        },
                        function (data)
                        {
                            $("#activity-modal").find(".modal-body").html(data);
                            $(".modal-body").html(data);
                            $(".modal-title").html("เปิดดูข้อมูลสมาชิก");
                            $("#activity-modal").modal("show");
                        }
                    );
                });
            $(".activity-update-link").click(function(e) {
                    var fID = $(this).closest("tr").data("key");
                    $.get(
                        "update",
                        {
                            id: fID
                        },
                        function (data)
                        {
                            $("#activity-modal").find(".modal-body").html(data);
                            $(".modal-body").html(data);
                            $(".modal-title").html("แก้ไขข้อมูลสมาชิก");
                            $("#activity-modal").modal("show");
                        }
                    );
                });
            
        }
        init_click_handlers(); //first run
        $("#customer_pjax_id").on("pjax:success", function() {
          init_click_handlers(); //reactivate links in grid after pjax update
        });');?>