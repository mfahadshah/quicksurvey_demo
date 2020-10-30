<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.7
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\components\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

class PageSizeDropDownList extends Widget
{
    public $selectedSize;

    public $sizes;

    public $cssClass;

    public $url;

    public function init()
    {
        parent::init();
        if ($this->selectedSize === null) {
            $this->selectedSize = 5;
        }
        if ($this->sizes === null) {
            $this->sizes = [
                '5' => '5',
                '10' => '10',
                '25' => '25',
                '50' => '50',
                '100' => '100',
                '500' => '500',
            ];
        }
        if ($this->cssClass === null) {
            $this->cssClass = 'form-control page-size';
        }
        if ($this->url === null) {
            $this->url = Url::to(['/ajax/grid-view-page-size']);
        }
    }

    public function run()
    {
        $this->view->registerJs("$('body').on('change', '.grid-view .page-size', function() {
            if (this.value) {
                var url = '{$this->url}';
                $.post(url, { size: this.value })
                    .done(function(response) {
                        if (response.success) {
                            window.location.reload();                    
                        }
                    });
            }
        });");
        return Html::dropDownList('page-size', $this->selectedSize, $this->sizes, [ 'class' => $this->cssClass ]);
    }
}