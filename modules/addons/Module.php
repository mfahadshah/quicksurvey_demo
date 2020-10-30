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

namespace app\modules\addons;

use app\helpers\FileHelper;
use app\modules\addons\models\Addon;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\filters\AccessControl;

class Module extends \yii\base\Module implements BootstrapInterface
{

    public $defaultRoute = 'admin/index';
    public $controllerLayout = '@app/views/layouts/main';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'matchCallback' => function () {
                            // CheckController is a public controller inside each add-on
                            if (Yii::$app->controller->id == "check") {
                                return true;
                            }

                            // Check for admin permission
                            // Note: Check for Yii::$app->user first because it doesn't exist in console commands (throws exception)
                            if (!empty(Yii::$app->user) && Yii::$app->user->can("admin")) {
                                return true;
                            }

                            // By Default, Denied Access
                            return false;
                        }
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Yii::setAlias('@addons', '@app/modules/addons/modules');

        // set up i8n
        if (empty(Yii::$app->i18n->translations['addon'])) {
            Yii::$app->i18n->translations['addon'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en-US',
                'basePath' => '@app/modules/addons/messages',
                //'forceTranslation' => true,
            ];
        }

        $this->setModules($this->getActiveAddOns());
    }

    /**
     * Bootstrap method to be called during application bootstrap stage.
     *
     * @param \yii\base\Application $app the application currently running
     */
    public function bootstrap($app)
    {
        $this->attachEvents($app);
    }

    /**
     * Attaches global and class-level event handlers from sub-modules
     *
     * @param \yii\base\Application $app the application currently running
     */
    public function attachEvents($app)
    {
        foreach ($this->getModules() as $moduleID => $config) {

            $module = $this->getModule($moduleID);

            if ($module instanceof EventManagerInterface) {

                /** @var EventManagerInterface $module */

                // Attach global events
                $globalEvents = $module->attachGlobalEvents();
                if (is_array($globalEvents)) {
                    foreach ($globalEvents as $eventName => $handler) {
                        $app->on($eventName, $handler);
                    }
                }

                // Attach class events
                $classEvents = $module->attachClassEvents();
                if (is_array($classEvents)) {
                    foreach ($classEvents as $className => $events) {
                        if (is_array($events)) {
                            foreach ($events as $eventName => $handlers) {
                                if (is_array($handlers)) {
                                    foreach ($handlers as $handler) {
                                        if (is_array($handler) && is_callable($handler[0])) {
                                            $data = isset($handler[1]) ? array_pop($handler) : null;
                                            $append = isset($handler[2]) ? array_pop($handler) : null;
                                            Event::on($className, $eventName, $handler[0], $data, $append);
                                        } elseif (is_callable($handler)) {
                                            Event::on($className, $eventName, $handler);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getActiveAddOns()
    {
        // Disable Add-On if it's not found
        // Absolute path to addOns directory
        $addOnsDirectory = Yii::getAlias('@addons');

        // Each sub-directory name is an addOn id
        $addOns = FileHelper::scandir($addOnsDirectory);
        $installedAddOns = Addon::find()->all();
        foreach ($installedAddOns as $installedAddOn) {
            // Disable removed add-on
            if (!in_array($installedAddOn->id, $addOns)) {
                $installedAddOn->status = false;
                $installedAddOn->save(false);
            }
        }

        $activeAddOns = [];

        foreach ($installedAddOns as $installedAddOn) {
            if ($installedAddOn->status) {
                $activeAddOns[$installedAddOn->id]['class'] = $installedAddOn->class;
            }
        }

        return $activeAddOns;
    }
}
