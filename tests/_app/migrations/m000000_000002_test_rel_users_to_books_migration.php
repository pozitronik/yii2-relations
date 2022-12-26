<?php
declare(strict_types = 1);

use pozitronik\relations\models\RelationMigration;

/**
 * Class m000000_000000_test_user_migration
 */
class m000000_000002_test_rel_users_to_books_migration extends RelationMigration {

	public string $table_name = 'rel_users_to_books';
	public string $first_key = 'user_id';
	public string $second_key = 'book_id';

}
