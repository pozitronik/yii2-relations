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
	 * @return void
	 */
	public function testBasic():void {
		static::assertCount(4, Users::find()->all());
		static::assertCount(10, Books::find()->all());
	}

	/**
	 * @return void
	 */
	public function testCreateRelationsViaId():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [1, 5, 7];

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
	public function testCreateRelationsViaModels():void {
		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [Books::find()->where(['id' => 2])->one(), Books::find()->where(['id' => 6])->one()];
		$user->save();

		$relations = RelUsersToBooks::find()->all();
		static::assertCount(2, $relations);

		$user->refresh();
		static::assertCount(2, $user->relatedBooks);
		static::assertInstanceOf(Books::class, $user->relatedBooks[0]);
	}

	/**
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
	 * @return void
	 */
	public function testRelationsSaveAfterPrimary():void {
		Yii::$app->set('relations', [
			'class' => RelationsTrait::class,
			'afterPrimaryMode' => true
		]);

		$reflectedClass = new ReflectionClass(RelationsTrait::class);
		$reflectedClass->setStaticPropertyValue('_modeAfterPrimary', null);


		/** @var Users $user */
		$user = Users::find()->where(['login' => 'admin'])->one();
		$user->relatedBooks = [2, 4, 10];
		$relations = RelUsersToBooks::find()->all();
		static::assertCount(0, $relations);
		static::assertCount(0, $user->relatedBooks);

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
