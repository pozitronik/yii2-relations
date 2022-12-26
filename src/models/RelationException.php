<?php
declare(strict_types = 1);

namespace pozitronik\relations\models;

use pozitronik\helpers\Utils;
use Throwable;
use yii\base\Exception;
use yii\base\Model;

/**
 * Class RelationException
 */
class RelationException extends Exception {

	/**
	 * @param string $message
	 * @param Model|null $relation
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message = "", ?Model $relation = null, int $code = 0, ?Throwable $previous = null) {
		if (null !== $relation && $relation->hasErrors()) {
			$message .=" /n".Utils::Errors2String($relation->errors);
		}
		parent::__construct($message, $code, $previous);
	}

	/**
	 * @inheritDoc
	 */
	public function getName():string {
		return 'Relation exception';
	}

}