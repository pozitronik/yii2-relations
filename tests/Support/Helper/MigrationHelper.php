<?php
declare(strict_types = 1);

namespace Tests\Support\Helper;

use yii\base\InvalidRouteException;
use yii\console\controllers\MigrateController;
use yii\console\Exception;

/**
 *
 */
class MigrationHelper {
	/**
	 * @return void
	 * @throws InvalidRouteException
	 * @throws Exception
	 */
	public static function migrate():void {
		$migrationController = new MigrateController('migrations', \Yii::$app);
		$migrationController->interactive = false;
		$migrationController->runAction('up');
	}
}