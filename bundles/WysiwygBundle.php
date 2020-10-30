<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.0
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\bundles;

use yii\web\AssetBundle;

/**
 * Class WysiwygBundle
 *
 * @package app\bundles
 */
class WysiwygBundle extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web/static_files/js/form.builder/lib/tinymce';
    public $js = [
        'tinymce.min.js',
    ];
    public $depends = [
        'app\bundles\AppBundle', // Load jquery.js and bootstrap.js first
    ];
}
