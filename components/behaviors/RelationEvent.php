<?php

namespace app\components\behaviors;

use yii\base\Event;

class RelationEvent extends Event
{
    const BEFORE_VALIDATE = 'beforeRelationValidate';
    const BEFORE_SAVE = 'beforeRelationSave';
    const AFTER_SAVE = 'afterRelationSave';
    public $isValid = true;
    public $child;
    public $relation;
    public $index;
}