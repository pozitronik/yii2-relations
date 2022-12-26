<?php

declare(strict_types = 1);

namespace Tests\Support;

use Codeception\Actor;
use Yii;
use yii\base\InvalidRouteException;
use yii\console\controllers\MigrateController;
use yii\console\Exception;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
 */
class UnitTester extends Actor {
	use _generated\UnitTesterActions;

	/**
	 * Applies all test app migrations (assume calling this method before tests)
	 * @return void
	 * @throws InvalidRouteException
	 * @throws Exception
	 */
	public function migrate():void {
		$migrationController = new MigrateController('migrations', Yii::$app);
		$migrationController->interactive = false;
		$migrationController->runAction('up');
	}
}
