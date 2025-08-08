<?php
declare(strict_types = 1);

use pozitronik\relations\models\RelationMigration;

/**
 * Class m000000_000004_test_rel_users_to_partners_migration
 */
class m000000_000004_test_rel_users_to_partners_migration extends RelationMigration {

	public const TABLE_NAME = 'rel_users_to_partners';

	/**
	 * {@inheritdoc}
	 */
	public function safeUp():void {
		$this->createTable(self::TABLE_NAME, [
			'id' => $this->primaryKey()->comment('Идентификатор'),
			'partner_id' => $this->integer()->notNull()->comment('Идентификатор партнёра'),
			'user_id' => $this->integer()->notNull()->comment('Идентификатор пользователя'),
		]);

		$this->addForeignKey(
			'fk-'.self::TABLE_NAME.'-partner_id',
			self::TABLE_NAME,
			'partner_id',
			'partners',
			'id',
			'CASCADE',
			'CASCADE'
		);

		$this->createIndex(
			'idx-'.self::TABLE_NAME.'-partner_id',
			self::TABLE_NAME,
			'partner_id'
		);

		$this->addForeignKey(
			'fk-'.self::TABLE_NAME.'-user_id',
			self::TABLE_NAME,
			'user_id',
			'users',
			'id',
			'CASCADE',
			'CASCADE'
		);

		$this->createIndex(
			'idx-'.self::TABLE_NAME.'-user_id',
			self::TABLE_NAME,
			'user_id'
		);

		$this->createIndex(
			'idx-'.self::TABLE_NAME.'-user_id-partner_id',
			self::TABLE_NAME,
			['user_id', 'partner_id'],
			true
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function safeDown():void {
		$this->dropTable(self::TABLE_NAME);
	}

}
