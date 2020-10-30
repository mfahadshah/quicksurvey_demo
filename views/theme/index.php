<?php

use app\components\widgets\ActionBar;
use app\components\widgets\GridView;
use app\components\widgets\PageSizeDropDownList;
use Carbon\Carbon;
use kartik\datecontrol\Module as DateControlModule;
use kartik\switchinput\SwitchInput;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $searchModel app\models\search\ThemeSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Themes');
$this->params['breadcrumbs'][] = $this->title;

/** @var kartik\datecontrol\Module $dateControlModule */
$dateControlModule = \Yii::$app->getModule('datecontrol');

Carbon::setLocale(substr(Yii::$app->language, 0, 2)); // eg. en-US to en

$options = array(
    'currentPage' => Url::toRoute(['index']), // Used by filters
);

// Pass php options to javascript
$this->registerJs("var options = ".json_encode($options).";", View::POS_BEGIN, 'form-options');
?>
<div class="theme-index">

    <?php
    $colorPluginOptions =  [
        'showPalette' => true,
        'showPaletteOnly' => true,
        'showSelectionPalette' => true,
        'showAlpha' => false,
        'allowEmpty' => false,
        'preferredFormat' => 'name',
        'palette' => [
            [
                "white", "black", "grey", "silver", "gold", "brown",
            ],
            [
                "red", "orange", "yellow", "indigo", "maroon", "pink"
            ],
            [
                "blue", "green", "violet", "cyan", "magenta", "purple",
            ],
        ]
    ];
    $gridColumns = [
        [
            'class' => '\kartik\grid\CheckboxColumn',
            'headerOptions' => ['class'=>'kartik-sheet-style'],
            'rowSelectedClass' => GridView::TYPE_WARNING,
        ],
        [
            'attribute'=> 'name',
            'format' => 'raw',
            'value' => function ($model) {
                return Html::a(Html::encode($model->name), ['theme/view', 'id' => $model->id]);
            },
        ],
        'description',
        [
            'attribute'=>'color',
            'value'=>function ($model) {
                return "<span class='badge' style='background-color: {$model->color}'>&nbsp;</span>&nbsp;&nbsp;<code>" .
                $model->color . '</code>';
            },
            'filterType'=>GridView::FILTER_COLOR,
            'filterWidgetOptions'=>[
                'showDefaultPalette'=>false,
                'noSupport' => Yii::t('app', 'It is recommended you use an upgraded browser to display the {type} control properly.'),
                'pluginOptions'=>$colorPluginOptions,
            ],
            'vAlign'=>'middle',
            'format'=>'raw',
            'noWrap'=>true
        ],
        [
            'attribute' => 'author',
            'value' => 'author.username',
            'label' => Yii::t('app', 'Created by'),
        ],
        [
            'attribute'=> 'updated_at',
            'value' => function ($model) {
                return Carbon::createFromTimestampUTC($model->updated_at)->diffForHumans();
            },
            'label' => Yii::t('app', 'Updated'),
            'filterType'=> \kartik\grid\GridView::FILTER_DATE_RANGE,
            'filterWidgetOptions' => [
                'presetDropdown' => true,
                'convertFormat' => true,
                'containerTemplate' => '
        <div class="form-control kv-drp-dropdown">
            <i class="glyphicon glyphicon-calendar"></i>&nbsp;
            <span class="range-value">{value}</span>
            <span><b class="caret"></b></span>
        </div>
        {input}
',
                'pluginOptions' => [
                    'locale' => [
                        'format' => $dateControlModule->getDisplayFormat(DateControlModule::FORMAT_DATE),
                        'separator' => ' - ',
                    ],
                    'opens' => 'left'
                ]
            ],
        ],
        ['class' => 'kartik\grid\ActionColumn',
            'dropdown'=>true,
            'dropdownButton' => ['class'=>'btn btn-primary'],
            'dropdownOptions' => ['class' => 'pull-right'],
            'buttons' => [
                //view button
                'view' => function ($url) {
                    $options = array_merge([
                        'title' => Yii::t('app', 'View'),
                        'aria-label' => Yii::t('app', 'View'),
                        'data-pjax' => '0',
                    ], []);
                    return '<li>'.Html::a('<span class="glyphicon glyphicon-eye-open"></span> ' .
                        Yii::t('app', 'View Record'), $url, $options).'</li>';
                },
                //update button
                'update' => function ($url) {
                    $options = array_merge([
                        'title' => Yii::t('app', 'Update'),
                        'aria-label' => Yii::t('app', 'Update'),
                        'data-pjax' => '0',
                    ], []);
                    return '<li>'.Html::a('<span class="glyphicon glyphicon-pencil"></span> ' .
                        Yii::t('app', 'Update'), $url, $options).'</li>';
                },
                //delete button
                'delete' => function ($url) {
                    $options = array_merge([
                        'title' => Yii::t('app', 'Delete'),
                        'aria-label' => Yii::t('app', 'Delete'),
                        'data-confirm' => Yii::t('app', 'Are you sure you want to delete this theme? All data related to this item will be deleted. This action cannot be undone.'),
                        'data-method' => 'post',
                        'data-pjax' => '0',
                    ], []);
                    return '<li>'.Html::a('<span class="glyphicon glyphicon-bin"></span> ' .
                        Yii::t('app', 'Delete'), $url, $options).'</li>';
                },
            ],
        ],
    ];
    ?>

    <?= GridView::widget([
        'id' => 'theme-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => $gridColumns,
        'resizableColumns' => false,
        'pjax' => false,
        'export' => false,
        'responsive' => true,
        'bordered' => false,
        'striped' => true,
        'panelTemplate' => Html::tag('div', '{panelHeading}{panelBefore}{items}{panelFooter}', [
            'class' => 'panel {type}']),
        'panel'=>[
            'type'=>GridView::TYPE_INFO,
            'heading'=> Yii::t('app', 'Themes').' <small class="panel-subtitle hidden-xs">'.
                Yii::t('app', 'Style & brand your online forms').'</small>',
            'before'=> ActionBar::widget([
                'grid' => 'theme-grid',
                'templates' => [
                    '{create}' => ['class' => 'col-xs-6 col-md-8'],
                    '{filters}' => ['class' => 'col-xs-2 col-md-2 no-padding'],
                    '{bulk-actions}' => ['class' => 'col-xs-4 col-md-2'],
                ],
                'bulkActionsItems' => [
                    'General' => ['general-delete' => Yii::t('app', 'Delete')],
                ],
                'bulkActionsOptions' => [
                    'options' => [
                        'general-delete' => [
                            'url' => Url::toRoute('delete-multiple'),
                            'data-confirm' => Yii::t('app', 'Are you sure you want to delete these themes? All data related to each item will be deleted. This action cannot be undone.'),
                        ],
                    ],
                    'class' => 'form-control',
                ],
                'elements' => [
                    'create' =>
                        Html::a(
                            '<span class="glyphicon glyphicon-plus"></span> ' . Yii::t('app', 'Create Theme'),
                            ['create'],
                            ['class' => 'btn btn-primary']
                        ) . ' ' .
                        Html::a(Yii::t('app', 'Do you know how to customize your form?'), ['/form'], [
                            'data-toggle' => 'tooltip',
                            'data-placement'=> 'top',
                            'title' => Yii::t('app', 'It’s very easy. Just go to Form Manager, then click on the form Settings to customize and click on the UI Settings tab. Finally choose your theme.'),
                            'class' => 'text hidden-xs hidden-sm']),
                            'filters' => SwitchInput::widget(
                                [
                                    'name'=>'filters',
                                    'type' => SwitchInput::CHECKBOX,
                                    'pluginOptions' => [
                                        'size' => 'mini',
                                        'animate' => false,
                                        'labelText' => Yii::t('app', 'Filter'),
                                    ],
                                    'pluginEvents' => [
                                        "switchChange.bootstrapSwitch" => "function(event, state) {
                                            if (state) {
                                                $('.filters').fadeIn()
                                                localStorage.setItem('gridView.filters', 1);
                                            } else {
                                                $('.filters').fadeOut()
                                                localStorage.setItem('gridView.filters', 0);
                                                window.location = options.currentPage;
                                            }
                                        }",
                                    ],
                                    'containerOptions' => ['style' => 'margin-top: 6px; text-align: right'],
                                ]
                            ),
                ],
                'class' => 'form-control',
            ]),
        ],
        'replaceTags' => [
            '{pageSize}' => function($widget) {
                $html = '';
                if ($widget->panelFooterTemplate !== false) {
                    $selectedSize = Yii::$app->user->preferences->get('GridView.pagination.pageSize');
                    return PageSizeDropDownList::widget(['selectedSize' => $selectedSize]);
                }
                return $html;
            },
        ],
        'panelFooterTemplate' => '
            <div class="kv-panel-pager">
                {pageSize}
                {pager}
            </div>
        ',
        'toolbar' => false,
    ]); ?>

</div>
<?php
$js = <<< 'SCRIPT'

$(function () {
    // Tooltips
    $("[data-toggle='tooltip']").tooltip();
    // Filters
    var state = localStorage.getItem('gridView.filters');
    if (typeof state !== undefined && state == 1) {
        $('input[name="filters"]').bootstrapSwitch('state', true);
    } else {
        $('input[name="filters"]').bootstrapSwitch('state', false);
    }
});

SCRIPT;
// Register tooltip/popover initialization javascript
$this->registerJs($js);