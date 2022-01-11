<?php
declare(strict_types = 1);

namespace pozitronik\relations\models;

use yii\base\Exception;

/**
 * Class RelationException
 */
class RelationException extends Exception {
	/**
	 * @inheritDoc
	 */
	public function getName():string {
		return 'Relation exception';
	}

}