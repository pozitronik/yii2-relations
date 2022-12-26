<?php
declare(strict_types = 1);

namespace app\models;

use pozitronik\relations\traits\RelationsTrait;
use yii\db\ActiveRecord;

/**
 * @property int $user_id
 * @property int $book_id
 */
class RelUsersToBooks extends ActiveRecord {
	use RelationsTrait;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName():string {
		return 'rel_users_to_books';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules():array {
		return [
			[['user_id', 'book_id'], 'required'],
			[['user_id', 'book_id'], 'integer'],
			[['user_id', 'book_id'], 'unique', 'targetAttribute' => ['user_id', 'book_id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels():array {
		return [
			'id' => 'ID',
			'user_id' => 'User ID',
			'book_id' => 'Book ID',
		];
	}

}