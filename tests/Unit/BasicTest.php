<?php
declare(strict_types = 1);

namespace Tests\Unit;

use app\fixtures\BooksFixture;
use app\fixtures\UsersFixture;
use app\models\Books;
use app\models\Users;
use Codeception\Test\Unit;
use Tests\Support\UnitTester;

/**
 * ExampleTest
 */
class BasicTest extends Unit {

	protected UnitTester $tester;

	/**
	 * @inheritDoc
	 */
	protected function _before():void {
		$this->tester->migrate();
		$this->tester->grabFixture('users');
	}

	/**
	 * @return string[]
	 */
	public function _fixtures() {
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

}
