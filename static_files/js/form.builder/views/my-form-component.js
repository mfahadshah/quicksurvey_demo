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
    "jquery", "underscore", "backbone", "tinyMCE",
    "views/component",
    "helper/pubsub"
], function(
    $, _, Backbone, tinyMCE,
    ComponentView,
    PubSub
    ){
    return ComponentView.extend({
        events:{
            "click"   : "preventPropagation" //stops checkbox / radio reacting.
            , "mousedown" : "mouseDownHandler"
            , "mouseup"   : "mouseUpHandler"
        }

        , mouseDownHandler : function(mouseDownEvent){
            mouseDownEvent.stopPropagation();
            mouseDownEvent.preventDefault();
            var that = this;
            // Popover
            $(".popover").remove();
            this.$el.popover("show");
            $(".popover #save").on("click", this.saveHandler(that));
            $(".popover #copy").on("click", this.copyHandler(that));
            $(".popover #delete").on("click", this.deleteHandler(that));
            $(".popover #cancel").on("click", this.cancelHandler(that));
            // Add drag event for all
            $("body").on("mousemove", function(mouseMoveEvent){
                if ( Math.abs(mouseDownEvent.pageX - mouseMoveEvent.pageX) > 10 ||
                     Math.abs(mouseDownEvent.pageY - mouseMoveEvent.pageY) > 10 )
                {
                    that.$el.popover('destroy');
                    PubSub.trigger("myComponentDrag", mouseDownEvent, that.model);
                    that.mouseUpHandler();
                }
            });
        }

        , preventPropagation: function(e) {
            e.stopPropagation();
            e.preventDefault();
        }

        , mouseUpHandler : function(mouseUpEvent) {
            // Add Wysiwyg editor
            var config = {
                selector: '#snippet',
                base_url: options.libUrl + 'tinymce',
                suffix: '.min',
                plugins: 'advlist autolink link image lists charmap hr anchor ' +
                    'searchreplace visualblocks visualchars code fullscreen insertdatetime nonbreaking ' +
                    'save table directionality paste',
                toolbar: 'undo redo | styleselect | bold italic | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | fullscreen code',
                table_default_attributes: {
                    class: 'table'
                },
                table_class_list: [
                    {title: 'None', value: ''},
                    {title: 'Table', value: 'table'},
                    {title: 'Condensed', value: 'table table-condensed'},
                    {title: 'Striped', value: 'table table-striped'},
                    {title: 'Bordered', value: 'table table-bordered'},
                    {title: 'Hover', value: 'table table-hover'},
                    {title: 'Striped & Hover', value: 'table table-stripped table-hover'},
                    {title: 'Bordered & Hover', value: 'table table-bordered table-hover'},
                    {title: 'Bordered, Stripped & Hover', value: 'table table-bordered table-hover'}
                ],
                setup: function (editor) {
                    editor.on('FullscreenStateChanged', function (e) {
                        $(editor.editorContainer).find(".tox-toolbar").toggleClass("tox-toolbar-fullscreen")
                    });
                }
            };

            // Removes editor
            if (tinyMCE.get(0)) {
                tinyMCE.remove();
            }

            // Add editor
            tinyMCE.init(config)
                .then(function(editors) {});

            $("body").off("mousemove");
        }

        , saveHandler : function(boundContext) {
            return function(mouseEvent) {
                mouseEvent.preventDefault();
                // Save editor's content
                if (tinyMCE.get(0)) {
                    // tinyMCE.get(0).save();
                    tinyMCE.triggerSave();
                }
                var fields = $(".popover .field");
                _.each(fields, function(e){

                    var $e = $(e)
                        , type = $e.attr("data-type")
                        , name = $e.attr("id");

                    switch(type) {
                        case "checkbox":
                            boundContext.model.setField(name, $e.is(":checked"));
                            break;
                        case "input":
                            boundContext.model.setField(name, $e.val());
                            break;
                        case "number":
                            boundContext.model.setField(name, $e.val());
                            break;
                        case "textarea":
                            boundContext.model.setField(name, $e.val());
                            break;
                        case "textarea-split":
                            boundContext.model.setField(name,
                                _.chain($e.val().split("\n"))
                                    .map(function(t){return $.trim(t)})
                                    .filter(function(t){return t.length > 0})
                                    .value()
                            );
                            break;
                        case "select":
                            var valarr = _.map($e.find("option"), function(e){
                                return {value: e.value, selected: e.selected, label:$(e).text()};
                            });
                            boundContext.model.setField(name, valarr);
                            break;
                    }
                });
                boundContext.model.trigger("change");
                $(".popover").remove();
            }
        }

        , copyHandler : function (boundContext) {
            return function(mouseEvent) {
                mouseEvent.preventDefault();
                // Copy model
                var originalModel = boundContext.model;
                var copiedModel = originalModel.clone();
                copiedModel.attributes = $.extend(true, {}, copiedModel.attributes);
                copiedModel.set('fresh', true);
                originalModel.trigger("change");
                PubSub.trigger("myComponentCopy", copiedModel, originalModel);
                $(".popover").remove();
            };
        }

        , deleteHandler : function (boundContext) {
            return function(mouseEvent) {
                mouseEvent.preventDefault();
                if (confirm(polyglot.t('alert.confirmToDeleteField'))) {
                    $(".popover").remove();
                    boundContext.model.trigger("remove");
                    PubSub.trigger("myComponentDelete", boundContext.model);
                }
            };
        }

        , cancelHandler : function(boundContext) {
            return function(mouseEvent) {
                mouseEvent.preventDefault();
                $(".popover").remove();
                boundContext.model.trigger("change");
            };
        }

    });
});