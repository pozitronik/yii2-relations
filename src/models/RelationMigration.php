<?php
declare(strict_types = 1);

namespace pozitronik\relations\models;

use yii\db\Migration;

/**
 * Class RelationMigration
 * @property string $table_name
 * @property string $first_key
 * @property string $second_key
 */
class RelationMigration extends Migration {

	public string $table_name;
	public string $first_key;
	public string $second_key;

	/**
	 * {@inheritdoc}
	 */
	public function safeUp() {
		$this->createTable($this->table_name, [
			'id' => $this->primaryKey(),
			$this->first_key => $this->integer()->notNull(),
			$this->second_key => $this->integer()->notNull(),
		]);

		$this->createIndex("{$this->first_key}_{$this->second_key}", $this->table_name, [$this->first_key, $this->second_key], true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function safeDown() {
		$this->dropTable($this->table_name);
	}

}