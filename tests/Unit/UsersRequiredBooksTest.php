<?php
declare(strict_types = 1);

namespace Tests\Unit;

use app\fixtures\BooksFixture;
use app\fixtures\UsersFixture;
use app\models\Books;
use app\models\RelUsersToBooks;
use app\models\Users;
use app\models\UsersRequiredBooks;
use Codeception\Test\Unit;
use pozitronik\relations\traits\RelationsTrait;
use ReflectionClass;
use Tests\Support\Helper\MigrationHelper;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\console\Exception;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;

/**
 * In that part of tests the `relatedBooks` relation is required
 */
class UsersRequiredBooksTest extends Unit {
	/**
	 * @return string[]
	 * @throws Exception
	 * @throws InvalidRouteException
	 */
	public function _fixtures():array {
		MigrationHelper::migrateFresh();
		return [
			'users' => UsersFixture::class,
			'books' => BooksFixture::class
		];
	}

	/**
	 * Sets AfterPrimaryMode. Note: change is permanent on all tests scope
	 * @param bool $mode
	 * @return void
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	private function setAfterPrimaryMode(bool $mode):void {
		Yii::$app->set('relations', [
			'class' => RelationsTrait::class,
			'afterPrimaryMode' => $mode
		]);
		/* Unset default trait property value*/
		$reflectedClass = new ReflectionClass(RelationsTrait::class);
		$reflectedClass->setStaticPropertyValue('_modeAfterPrimary', null);
		/* Unset trait model property value*/
		$reflectedClass = new ReflectionClass(RelUsersToBooks::class);
		$reflectedClass->setStaticPropertyValue('_modeAfterPrimary', null);

		static::assertEquals($mode, RelUsersToBooks::getAfterPrimaryMode());
	}

	/**
	 * Sets ClearOnEmptyMode. Note: change is permanent on all tests scope
	 * @param bool $mode
	 * @return void
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	private function setClearOnEmptyMode(bool $mode):void {
		Yii::$app->set('relations', [
			'class' => RelationsTrait::class,
			'clearOnEmptyMode' => $mode,
		]);
		/* Unset default trait property value*/
		$reflectedClass = new ReflectionClass(RelationsTrait::class);
		$reflectedClass->setStaticPropertyValue('_modeClearOnEmpty', null);
		/* Unset trait model property value*/
		$reflectedClass = new ReflectionClass(RelUsersToBooks::class);
		$reflectedClass->setStaticPropertyValue('_modeClearOnEmpty', null);

		static::assertEquals($mode, RelUsersToBooks::getClearOnEmptyMode());
	}

	/**
	 * Tests behaviour when afterPrimaryMode is disabled, and relation is required
	 * @return void
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	public function testRelationsSetRequiredEmptyImmediately():void {
		$this->setAfterPrimaryMode(false);//to be sure
		$this->setClearOnEmptyMode(true);
		/** @var Users $user */
		$user = UsersRequiredBooks::find()->where(['login' => 'admin'])->one();
		$book = Books::find()->where(['id' => '1'])->one();
		$user->relatedBooks = $book;
		static::assertCount(1, RelUsersToBooks::find()->all());
		$user->refresh();
		static::assertCount(1, $user->relatedBooks);
		$user->relatedBooks = null;
		static::assertCount(0, RelUsersToBooks::find()->all());
		$user->refresh();
		static::assertCount(0, $user->relatedBooks);

		/*when afterPrimaryMode is enabled, all relations should be saved only when primary model save*/
		static::assertFalse($user->save());

		$relations = RelUsersToBooks::find()->all();
		static::assertCount(3, $relations);

		$user->refresh();
		static::assertCount(3, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		$this->setAfterPrimaryMode(false);//return to default behaviour
	}

	/**
	 * Tests behaviour when afterPrimaryMode is enabled, and relation is required
	 * @return void
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	public function testRelationsSetRequiredEmptyAfterSave():void {
		$this->setAfterPrimaryMode(true);
		$this->setClearOnEmptyMode(true);
		/** @var Users $user */
		$user = UsersRequiredBooks::find()->where(['login' => 'admin'])->one();
		$book = Books::find()->where(['id' => '1'])->one();
		$user->relatedBooks = $book;
		$saved = $user->save();
		static::assertTrue($saved);
		static::assertCount(1, RelUsersToBooks::find()->all());
		$user->refresh();
		static::assertCount(1, $user->relatedBooks);
		$user->relatedBooks = null;
		static::assertCount(0, RelUsersToBooks::find()->all());
		$user->refresh();
		static::assertCount(0, $user->relatedBooks);
		/* the required rule won't allow to store empty relation */
		static::assertFalse($user->save());
		$this->setAfterPrimaryMode(false);//return to default behaviour
	}

}
