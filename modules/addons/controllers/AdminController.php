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

namespace app\modules\addons\controllers;

use Yii;
use Exception;
use yii\base\InvalidConfigException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use app\helpers\FileHelper;
use app\components\database\Migration;
use app\modules\addons\models\Addon;
use app\modules\addons\models\AddonSearch;
use app\modules\addons\helpers\SetupHelper;

/**
 * DefaultController implements the CRUD actions for Addon model.
 */
class AdminController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * List of all Add-ons.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new AddonSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Reload index action
     *
     * @return \yii\web\Response
     * @throws \Throwable
     */
    public function actionRefresh()
    {
        try {
            $this->refreshAddOnsList();

            // Show success alert
            Yii::$app->getSession()->setFlash('success', Yii::t(
                'addon',
                'The Add-ons list has been refreshed successfully.'
            ));

        } catch (Exception $e) {
            Yii::error($e);
            // Show success alert
            Yii::$app->getSession()->setFlash('danger', Yii::t(
                'addon',
                $e->getMessage()
            ));
        }

        return $this->redirect(['index']);
    }

    /**
     * Add / Remove Add-Ons automatically.
     *
     * @throws InvalidConfigException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    protected function refreshAddOnsList()
    {

        // Absolute path to addOns directory
        $addOnsDirectory = Yii::getAlias('@addons');

        // Each sub-directory name is an addOn id
        $addOns = FileHelper::scandir($addOnsDirectory);

        $installedAddOns = ArrayHelper::map(Addon::find()->select(['id','id'])->asArray()->all(), 'id', 'id');
        $newAddOns = array_diff($addOns, $installedAddOns);
        $removedAddOns = array_diff($installedAddOns, $addOns);

        // Uninstall removed addOns
        SetupHelper::uninstall($removedAddOns);

        // Install new addOns
        SetupHelper::install($newAddOns);

        // Update addOns versions
        SetupHelper::update($installedAddOns);

    }

    /**
     * Enable / Disable multiple Add-ons
     *
     * @param $status
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionUpdateStatus($status)
    {

        $addOns = Addon::findAll(['id' => Yii::$app->getRequest()->post('ids')]);

        if (empty($addOns)) {
            throw new NotFoundHttpException(Yii::t('addon', 'Page not found.'));
        } else {
            foreach ($addOns as $addOn) {
                $addOn->status = $status;
                $addOn->update();
            }
            Yii::$app->getSession()->setFlash(
                'success',
                Yii::t('addon', 'The selected items have been successfully updated.')
            );
            return $this->redirect(['index']);
        }
    }

    /**
     * Run DB Migration Up
     *
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionInstall()
    {
        $addOns = Addon::findAll(['id' => Yii::$app->getRequest()->post('ids')]);

        if (empty($addOns)) {

            throw new NotFoundHttpException(Yii::t('addon', 'Page not found.'));

        } else {

            // Flag
            $success = true;

            foreach ($addOns as $addOn) {
                try {
                    $migrationPath = Yii::getAlias('@addons') . DIRECTORY_SEPARATOR . $addOn->id . DIRECTORY_SEPARATOR .
                        'migrations';
                    $migrationTable = 'migration_' . $addOn->id;

                    if (is_dir($migrationPath) && $success) { // Stop next migrations
                        // Flush cache
                        Yii::$app->cache->flush();
                        // Run DB Migration
                        $migration = new Migration();
                        $migration->migrationPath = $migrationPath;
                        $migration->migrationTable = $migrationTable;
                        $migration->compact = true;
                        $r = $migration->up();
                        // Flag
                        $success = ($r === Migration::DONE) || ($r === Migration::NO_NEW_MIGRATION);

                    } else {
                        throw new Exception("There is an error with the migration path:" . $migrationPath);
                    }
                } catch (Exception $e) {
                    // Update flag
                    $success = false;
                    // Log error
                    Yii::error($e, __METHOD__);
                }

                if ($success) {
                    // Update Add-On Status
                    $addOn->status = $addOn::STATUS_ACTIVE;
                    $addOn->installed = $addOn::INSTALLED_ON;
                    $addOn->update();
                }

            }

            if ($success) {
                // Display success message
                Yii::$app->getSession()->setFlash(
                    'success',
                    Yii::t('addon', 'The selected items have been installed successfully.')
                );
            } else {
                // Display error message
                Yii::$app->getSession()->setFlash(
                    'danger',
                    Yii::t('addon', 'An error has occurred while installing your add-ons.')
                );
            }

            return $this->redirect(['index']);
        }
    }

    /**
     * Run DB Migration Down
     *
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionUninstall()
    {
        $addOns = Addon::findAll(['id' => Yii::$app->getRequest()->post('ids')]);

        if (empty($addOns)) {
            throw new NotFoundHttpException(Yii::t('addon', 'Page not found.'));
        } else {
            // Flag
            $success = true;
            foreach ($addOns as $addOn) {
                try {
                    $migrationPath = Yii::getAlias('@addons') . DIRECTORY_SEPARATOR . $addOn->id . DIRECTORY_SEPARATOR .
                        'migrations';
                    $migrationTable = 'migration_' . $addOn->id;

                    if (is_dir($migrationPath) && $success) { // Stop next migration
                        // Flush cache
                        Yii::$app->cache->flush();
                        // Run DB Migration
                        $migration = new Migration();
                        $migration->migrationPath = $migrationPath;
                        $migration->migrationTable = $migrationTable;
                        $migration->compact = true;
                        $r = $migration->down('all');
                        // Flag
                        $success = ($r === Migration::DONE) || ($r === Migration::NO_NEW_MIGRATION);
                    } else {
                        throw new Exception("There is an error with the migration path:" . $migrationPath);
                    }
                } catch (Exception $e) {
                    // Update flag
                    $success = false;
                    // Log error
                    Yii::error($e);
                }

                if ($success) {
                    $addOn->status = $addOn::STATUS_INACTIVE;
                    $addOn->installed = $addOn::INSTALLED_OFF;
                    $addOn->update();
                }

            }
            if ($success) {
                // Display success message
                Yii::$app->getSession()->setFlash(
                    'success',
                    Yii::t('addon', 'The selected items have been uninstalled successfully.')
                );
            } else {
                // Display error message
                Yii::$app->getSession()->setFlash(
                    'danger',
                    Yii::t('addon', 'An error has occurred while uninstalling your add-ons.')
                );
            }
            return $this->redirect(['index']);
        }
    }
}
