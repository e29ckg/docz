<aside class="main-sidebar">

    <section class="sidebar">
        <?= dmstr\widgets\Menu::widget(
            [
                'options' => ['class' => 'sidebar-menu tree', 'data-widget'=> 'tree'],
                'items' => [
                    ['label' => 'Menu', 'options' => ['class' => 'header text-center']],
                    ['label' => 'dashboard', 'icon' => 'file-code-o', 'url' => ['/#']],
                    ['label' => 'Gii', 'icon' => 'file-code-o', 'url' => ['/gii']],
                    ['label' => 'Debug', 'icon' => 'dashboard', 'url' => ['/debug']],
                    ['label' => 'about', 'url' => ['site/about'], 'visible' => !(Yii::$app->user->isGuest) && (Yii::$app->user->id == 2)],
                    ['label' => 'Login', 'url' => ['site/login'], 'visible' => Yii::$app->user->isGuest],
                    ['label' => 'เจ้าหน้าที่สารบรรณ', 'options' => ['class' => 'header text-center']],                    
                    ['label' => 'ระบบเอกสาร', 'icon' => 'file-code-o', 'url' => ['/bsdr/index']],
                    ['label' => 'นำเอกสารเข้าระบบ', 'icon' => 'file-code-o', 'url' => ['/bsdr/create']],
                    ['label' => 'Setting', 'options' => ['class' => 'header text-center']],
                    [
                        'label' => 'Admin',
                        'url' => '#',
                        'visible' => !Yii::$app->user->isGuest && (Yii::$app->user->id == 1),
                        'items' =>[
                            ['label' => 'User', 'icon' => 'file-code-o', 'url' => ['/admin/user'],],                            
                            ['label' => 'กำหนดสิทธ์', 'icon' => 'file-code-o', 'url' => ['/role/index'],],
                            ['label' => 'กำหนดขั้นตอน', 'icon' => 'file-code-o', 'url' => ['/docprofile/index'],],
                        ]
                    ],
                    [
                        'label' => 'Some tools',
                        'icon' => 'share',
                        'url' => '#',
                        'items' => [
                            ['label' => 'Gii', 'icon' => 'file-code-o', 'url' => ['/gii'],],
                            ['label' => 'Debug', 'icon' => 'dashboard', 'url' => ['/debug'],],
                            [
                                'label' => 'Level One',
                                'icon' => 'circle-o',
                                'url' => '#',
                                'items' => [
                                    ['label' => 'Level Two', 'icon' => 'circle-o', 'url' => '#',],
                                    [
                                        'label' => 'Level Two',
                                        'icon' => 'circle-o',
                                        'url' => '#',
                                        'items' => [
                                            ['label' => 'Level Three', 'icon' => 'circle-o', 'url' => '#',],
                                            ['label' => 'Level Three', 'icon' => 'circle-o', 'url' => '#',],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ) ?>

    </section>

</aside>
