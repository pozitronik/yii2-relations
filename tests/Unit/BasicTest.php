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

/**
 * ExampleTest
 */
class BasicTest extends Unit {

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
	}

	/**
	 * Test the `modeAfterPrimary` mode
	 * @return void
	 * @see RelationsTrait::$_modeAfterPrimary
	 */
	public function testRelationsSaveAfterPrimary():void {
		Yii::$app->set('relations', [
			'class' => RelationsTrait::class,
			'afterPrimaryMode' => true
		]);
		/*Change default trait property value*/
		$reflectedClass = new ReflectionClass(RelationsTrait::class);
		$reflectedClass->setStaticPropertyValue('_modeAfterPrimary', null);
		/*Change trait model property value*/
		$reflectedClass = new ReflectionClass(RelUsersToBooks::class);
		$reflectedClass->setStaticPropertyValue('_modeAfterPrimary', null);

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

	}

	/**
	 * @return void
	 */
	public function testDeleteModel():void {

	}

}
