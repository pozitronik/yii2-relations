<?php
declare(strict_types = 1);

namespace Tests\Unit;

use app\fixtures\BooksFixture;
use app\fixtures\UsersFixture;
use app\models\Books;
use app\models\RelUsersToBooks;
use app\models\Users;
use Codeception\Test\Unit;
use pozitronik\relations\traits\RelationsTrait;
use ReflectionClass;
use Tests\Support\Helper\MigrationHelper;
use Tests\Support\UnitTester;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * RelationsTest
 */
class RelationsTest extends Unit {

	protected UnitTester $tester;

	/**
	 * @return string[]
	 */
	public function _fixtures() {
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
	 * Tests, that all migrations and fixtures were applied
	 * @return void
	 */
	public function testBasic():void {
		static::assertCount(4, Users::find()->all());
		static::assertCount(10, Books::find()->all());
	}

	/**
	 * Tests, that related model can be added via simple assignment
	 * @return void
	 */
	public function testCreateRelationsSimple():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		static::assertCount(0, $user->relatedBooks);
		$user->relatedBooks = Books::find()->where(['id' => 10])->one();
		$user->refresh();
		static::assertCount(1, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		static::assertEquals(Books::find()->where(['id' => 10])->one(), $user->relatedBooks[0]);
	}

	/**
	 * Tests, that related models can be added via their id
	 * @return void
	 */
	public function testCreateRelationsViaId():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [1, 5, '7'];
		$user->refresh();
		static::assertCount(3, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		static::assertEquals($user->relatedBooks, Books::find()->where(['id' => ArrayHelper::getColumn($user->relatedBooks, 'id')])->all());
	}

	/**
	 * Tests, that related models can be assigned as object array
	 * @return void
	 */
	public function testCreateRelationsViaModels():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = Books::find()->where(['id' => [2, 6]])->all();
		$user->refresh();
		static::assertCount(2, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		static::assertEquals($user->relatedBooks, Books::find()->where(['id' => ArrayHelper::getColumn($user->relatedBooks, 'id')])->all());
	}

	/**
	 * Tests, that related models can be added via any way, even if they mixed
	 * @return void
	 */
	public function testCreateRelationsViaMixed():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [1, '2', Books::find()->where(['id' => 3])->one()];
		$user->save();

		$relations = RelUsersToBooks::find()->all();
		static::assertCount(3, $relations);

		$user->refresh();
		static::assertCount(3, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		static::assertEquals($user->relatedBooks, Books::find()->where(['id' => ArrayHelper::getColumn($user->relatedBooks, 'id')])->all());
	}

	/**
	 * Test the `modeAfterPrimary` mode
	 * @return void
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	public function testRelationsSaveAfterPrimary():void {
		$this->setAfterPrimaryMode(true);
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [2, 4, 10];
		$relations = RelUsersToBooks::find()->all();
		static::assertCount(0, $relations);
		static::assertCount(0, $user->relatedBooks);
		/*when afterPrimaryMode is enabled, all relations should be saved only when primary model save*/
		$user->save();

		$relations = RelUsersToBooks::find()->all();
		static::assertCount(3, $relations);

		$user->refresh();
		static::assertCount(3, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
		$this->setAfterPrimaryMode(false);//return to default behaviour
	}

	/**
	 * @return void
	 */
	public function testEditRelations():void {

	}

	/**
	 * @return void
	 */
	public function testDeleteRelations():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = Books::find()->where(['id' => [2, 6]])->all();
		$user->refresh();
		static::assertCount(2, RelUsersToBooks::find()->all());
		$user->relatedBooks = null;
		static::assertCount(2, RelUsersToBooks::find()->all());
		/* Changes behaviour: now empty assignments delete all relations*/
		$this->setClearOnEmptyMode(true);
		$user->relatedBooks = null;
		static::assertCount(0, RelUsersToBooks::find()->all());
		$this->setClearOnEmptyMode(false);
	}

	/**
	 * If model deleted, its relations should be deleted too
	 * @return void
	 */
	public function testDeleteModel():void {
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = Books::find()->where(['id' => [2, 6]])->all();
		$user->refresh();
		static::assertCount(2, RelUsersToBooks::find()->all());
		$user->delete();
		static::assertCount(0, RelUsersToBooks::find()->all());
	}

}
