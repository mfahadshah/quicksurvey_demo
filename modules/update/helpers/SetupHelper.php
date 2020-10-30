<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.1
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\modules\update\helpers;

use Yii;
use Exception;
use app\components\database\Migration;

class SetupHelper
{

    /**
     * Runs new migrations
     *
     * @param int $numberOfMigrations
     * @return array
     */
    public static function runMigrations($numberOfMigrations = null)
    {
        try {

            $migrationPath = Yii::getAlias('@app/migrations');

            if (is_dir($migrationPath)) {

                // Flush cache
                Yii::$app->cache->flush();
                // Run DB Migration
                $migration = new Migration();
                $migration->migrationPath = $migrationPath;
                $migration->compact = true;
                $r = $migration->up($numberOfMigrations);

                // Verify response
                if ($r === Migration::DONE) {
                    return ['success' => 1, 'message' => 'Migrated up successfully.'];
                } elseif ($r === Migration::NO_NEW_MIGRATION) {
                    return ['success' => 1, 'message' => 'No new migration found. Your system is up-to-date.'];
                } else {
                    return ['success' => 0, 'message' => 'An error occurred during the migration process.'];
                }
            }

            return ['success' => 0, 'message' => 'No such the Migrations directory.'];

        } catch (Exception $e) {
            // Log error
            Yii::error($e, __METHOD__);
            return ['success' => 0, 'message' => $e->getMessage()];
        }
    }
}
