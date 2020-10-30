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

namespace app\components;

use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Class Bootstrap
 * @package app\components
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {

        $app->on(Application::EVENT_BEFORE_REQUEST, function () use ($app) {

            try {

                /*******************************
                /* Mailer
                /*******************************/

                // Change transport class to Sendinblue or SMTP
                $defaultMailerTransport = !empty($app->params['App.Mailer.transport']) ? $app->params['App.Mailer.transport'] : '';

                if ($app->settings->get('mailerTransport', 'app', $defaultMailerTransport) === 'sendinblue') {

                    // Set Sendinblue mail component as mailer
                    $app->set('mailer', [
                        'class' => 'app\components\sendinblue\Mailer',
                        'apiKey' => $app->settings->get('sendinblue.key'),
                    ]);

                } else {

                    // Set default transport class with PHP mail()
                    $transport = [
                        'class' => 'Swift_MailTransport',
                    ];

                    // Set an SMTP account
                    if ($app->settings->get('mailerTransport', 'app', $defaultMailerTransport) === 'smtp') {
                        $transport = [
                            'class' => 'Swift_SmtpTransport',
                            'host' => $app->settings->get("smtp.host"),
                            'username' => $app->settings->get("smtp.username"),
                            'password' => base64_decode($app->settings->get("smtp.password")),
                            'port' => $app->settings->get("smtp.port"),
                            'encryption' => $app->settings->get("smtp.encryption") == 'none'?
                                null :
                                $app->settings->get("smtp.encryption"),
                        ];
                    }

                    // Set mail queue component as mailer
                    $app->set('mailer', [
                        'class' => 'app\components\queue\MailQueue',
                        'mailsPerRound' => 10,
                        'maxAttempts' => 3,
                        'transport' => $transport,
                        'messageConfig' => [
                            'charset' => 'UTF-8',
                        ]
                    ]);
                }

                /*******************************
                /* User session
                /*******************************/

                if (isset($app->user) && !$app->user->isGuest) {
                    /** @var \app\models\Profile $profile */
                    $profile = $app->user->identity->profile;

                    // Setting the timezone to the current users timezone
                    if (isset($profile->timezone)) {
                        $app->setTimeZone($profile->timezone);
                    }

                    // Setting the language to the current users language
                    if (isset($profile->language)) {
                        $app->language = $profile->language;
                    }
                }

                /**
                 * Fix https issue with cloudflare when it's needed
                 */
                if (isset($_SERVER['HTTP_CF_VISITOR'])) {
                    if (preg_match('/https/i', $_SERVER['HTTP_CF_VISITOR'])) {
                        $_SERVER['HTTPS'] = 'On';
                        $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
                        $_SERVER['SERVER_PORT'] = 443;
                    }
                }

            } catch (\Exception $e) {
                // Do nothing
            }

        });

        /*******************************
        /* Event Handlers
        /*******************************/

        $app->on(
            'app.form.updated',
            ['app\events\handlers\FormEventHandler', 'onFormUpdated']
        );

        $app->on(
            'app.form.submission.received',
            ['app\events\handlers\SubmissionEventHandler', 'onSubmissionReceived']
        );

        $app->on(
            'app.form.submission.accepted',
            ['app\events\handlers\SubmissionEventHandler', 'onSubmissionAccepted']
        );

        $app->on(
            'app.form.submission.rejected',
            ['app\events\handlers\SubmissionEventHandler', 'onSubmissionRejected']
        );

    }
}
