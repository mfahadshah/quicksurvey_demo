/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.6.7
 * @author Balu
 * @copyright Copyright (c) 2015 - 2018 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */
$( document ).ready(function() {

    /**
     * Change label on selected file
     * Change HTML Label Text once File has been selected
     *
     * Useful for custom designs
     */

    $('input[type=file]').on('change', function(e) {
        var $input = $(e.currentTarget);
        var $label = $input.parent().find('label');
        var fileName = e.target.files[0].name;
        $label.text(fileName);
    });

});
