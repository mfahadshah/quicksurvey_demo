/*!
 * Bootstrap 2.3.1 Form Builder
 * Copyright (C) 2012 Adam Moore
 * Licensed under MIT (https://github.com/minikomi/Bootstrap-Form-Builder/blob/gh-pages/LICENSE)
 */

/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @description JavaScript Form Builder for Easy Forms
 * @since 1.0
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

define([
    "jquery", "underscore", "backbone"
    , "text!templates/app/recaptcha.html"
    , "text!templates/popover/popover-main.html"
    , "text!templates/popover/popover-input.html"
    , "text!templates/popover/popover-number.html"
    , "text!templates/popover/popover-select.html"
    , "text!templates/popover/popover-textarea.html"
    , "text!templates/popover/popover-textarea-split.html"
    , "text!templates/popover/popover-checkbox.html"
    , "templates/component/templates"
    , "bootstrap"
    , "popover-extra-placements"
], function(
    $, _, Backbone
    , _reCAPTCHA
    , _PopoverMain
    , _PopoverInput
    , _PopoverNumber
    , _PopoverSelect
    , _PopoverTextArea
    , _PopoverTextAreaSplit
    , _PopoverCheckbox
    , _componentTemplates
    ){
    return Backbone.View.extend({
        tagName: "div"
        , className: "component"
        , initialize: function(){
            this.template = _.template(_componentTemplates[this.model.get("name")]);
            this.recaptchaTemplate = _.template(_reCAPTCHA);
            this.popoverTemplates = {
                "input" : _.template(_PopoverInput)
                , "number" : _.template(_PopoverNumber)
                , "select" : _.template(_PopoverSelect)
                , "textarea" : _.template(_PopoverTextArea)
                , "textarea-split" : _.template(_PopoverTextAreaSplit)
                , "checkbox" : _.template(_PopoverCheckbox)
            }
        }
        , render: function(withAttributes) {
            var that = this;

            // Split fields in basic and advanced
            var basicFields = {};
            var advancedFields = {};
            _.map( that.model.get("fields"), function( field, key ) {
                if ( field.advanced === true ) {
                    advancedFields[key] = field;
                } else {
                    basicFields[key] = field;
                }
            });

            // HTML of the basic and advanced fields
            var basicFieldsHtml =  _.reduce(basicFields, function(str, v, k){
                v["name"] = k;
                return str + that.popoverTemplates[v["type"]](v);
            }, "");
            var advancedFieldsHtml =  _.reduce(advancedFields, function(str, v, k){
                v["name"] = k;
                return str + that.popoverTemplates[v["type"]](v);
            }, "");

            // Get the HTML of the popover
            var content = _.template(_PopoverMain)({
                "title": polyglot.t(that.model.get("title")), // i18n
                "basicFields" : basicFieldsHtml,
                "advancedFields" : advancedFieldsHtml
            });

            // Return the Component HTML
            var fieldValues = that.model.getValues();

            if (withAttributes) { // For builder preview
                // Passing container CSS Class to parent component
                var containerClass = "";
                if (typeof fieldValues["containerClass"] !== "undefined") {
                    if (fieldValues["containerClass"] === "") {
                        containerClass = "col-xs-12";
                    } else {
                        containerClass = that.model.getField("containerClass");
                    }
                    fieldValues["containerClass"] = "";
                }
                return this.$el.html(
                        that.template({field: fieldValues})
                    ).attr({
                        "class"             : "component component-" + that.model.get("name") + " " + containerClass
                        , "data-content"    : content
                        , "data-title"      : polyglot.t(that.model.get("title")) // i18n
                        , "data-trigger"    : "manual"
                        , "data-placement"  : "rightTop"
                        , "data-html"       : true
                    });
            } else { // For source code
                // 12 columns by default
                if (typeof fieldValues["containerClass"] !== "undefined") {
                    if (fieldValues["containerClass"] === "") {
                        fieldValues["containerClass"] = "col-xs-12";
                    }
                }
                // If is a reCAPTCHA component return the html required for Google reCAPTCHA
                // See https://developers.google.com/recaptcha/docs/display
                if (that.model.get("name") === "recaptcha") {
                    fieldValues.siteKey = options.reCaptchaSiteKey;
                    return this.$el.html(
                        that.recaptchaTemplate(fieldValues)
                    );
                }
                // If not, parse the component with the component data
                return this.$el.html(
                    that.template({field: fieldValues})
                )
            }
        }
    });
});