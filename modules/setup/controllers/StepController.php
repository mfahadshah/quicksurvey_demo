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

namespace app\modules\setup\controllers;

use app\modules\setup\helpers\SetupHelper;
use app\modules\setup\models\forms\DBForm;
use app\modules\setup\models\forms\UserForm;
use Exception;
use Yii;
use yii\helpers\Url;
use yii\httpclient\Client;
use yii\web\Controller;
use yii\web\Cookie;
use yii\web\Response;

class StepController extends Controller
{
    public $layout = 'setup';

    private $activatePurchaseCode;

    private $activateDomain;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        Yii::$app->language = isset(Yii::$app->request->cookies['language']) ? (string)Yii::$app->request->cookies['language'] : 'en-US';

        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($this->action->id != '1') {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            if (!Yii::$app->session->has('purchase_code')) {
                Yii::$app->session->setFlash('warning', Yii::t('setup', 'Please enter a valid purchase code'));
                $this->redirect(['step/1']);
                return false;
            }
        }

        $this->activateDomain = Url::home(true);
        $this->activatePurchaseCode = base64_decode(SetupHelper::$purchaseCode);

        return true; // or false to not run the action
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function action1()
    {
        if ($language = Yii::$app->request->post('language')) {

            Yii::$app->language = $language;

            $languageCookie = new Cookie([
                'name' => 'language',
                'value' => $language,
                'expire' => time() + 60 * 60 * 24, // 1 day
            ]);

            Yii::$app->response->cookies->add($languageCookie);

            $purchase_code = Yii::$app->request->post('purchase_code', '');

            if (trim($purchase_code) == '') {
                Yii::$app->session->setFlash('warning', Yii::t('setup', 'Please enter a valid purchase code'));
                return $this->redirect(['step/1']);
            }

            Yii::$app->session->set('purchase_code', $purchase_code);
            return $this->redirect(['2']);
        }

        return $this->render('1');
    }

    public function action2()
    {
        return $this->render('2');
    }

    public function action3()
    {
        $dbForm = new DBForm();
        $connectionOk = false;

        if ($dbForm->load(Yii::$app->request->post()) && $dbForm->validate()) {
            if ($dbForm->testConnection()) {
                if (isset($_POST['test'])) {
                    $connectionOk = true;
                    Yii::$app->session->setFlash('success', Yii::t('setup', 'Database connection - ok'));
                }
                if (isset($_POST['save'])) {
                    $config = SetupHelper::createDatabaseConfig($dbForm->getAttributes());
                    if (SetupHelper::createDatabaseConfigFile($config) === true) {
                        return $this->render('4');
                    }
                    Yii::$app->session->setFlash('warning', Yii::t('setup', 'Unable to create db config file'));
                }
            }
        }

        return $this->render('3', ['model' => $dbForm, 'connectionOk' => $connectionOk]);
    }

    public function action4()
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            // Check if database was successfully installed

            $result = SetupHelper::executeSqlCommands();

            if (isset($result['success']) && $result['success'] === 0) {
                $result = SetupHelper::runMigrations();
            }

            return $result;
        }

        return '';
    }

    public function action5()
    {
        $userForm = new UserForm();

        if ($userForm->load(Yii::$app->request->post()) && $userForm->save()) {
            return $this->redirect(['step/6']);
        }

        return $this->render('5', [
            'model' => $userForm,
        ]);
    }

    public function action6()
    {
        // With Friendly Urls
        $cronUrl = Url::home(true) . 'cron?cron_key='.Yii::$app->params['App.Cron.cronKey'];

        try {
            $client = new Client();
            $response = $client->get($cronUrl)->send();

            if ($response->getContent() !== '') {
                // Without Friendly Urls
                $url = Url::to([
                    '/cron',
                    'cron_key' => Yii::$app->params['App.Cron.cronKey'],
                ], true);
                $cronUrl = str_replace("install","index", $url);
            }
        } catch (Exception $e) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw $e;
            }
        }

        return $this->render('6', [
            'cronUrl' => $cronUrl
        ]);
    }
}
