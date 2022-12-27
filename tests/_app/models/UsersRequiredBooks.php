<?php
declare(strict_types = 1);

namespace app\models;

/**
 * This is the same model as Users, but with relatedBooks requirement
 */
class UsersRequiredBooks extends Users {
	/**
	 * {@inheritdoc}
	 */
	public function rules():array {
		return [
			[['username', 'login', 'password'], 'string'],
			[['username', 'login', 'password'], 'required'],
			['relatedBooks', 'required']
		];
	}
}