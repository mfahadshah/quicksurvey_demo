<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.7.2
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\events;

use yii\mail\MailEvent;
use app\models\FormSubmission;

/**
 * Class SubmissionMailEvent
 * @package app\events
 */
class SubmissionMailEvent extends MailEvent
{
    const EVENT_NAME = 'app.form.submission.mail';
    const EVENT_TYPE_CONFIRMATION = 1;
    const EVENT_TYPE_NOTIFICATION = 2;

    /** @var string $type It can be 'confirmation' or 'notification' */
    public $type;
    /** @var boolean $async */
    public $async;
    /** @var array $tokens */
    public $tokens;
    /** @var FormSubmission */
    public $submission;
}