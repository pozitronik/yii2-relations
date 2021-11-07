<?php
declare(strict_types = 1);

namespace pozitronik\relations\models;

use yii\base\Component;
use yii\db\ActiveRecord;

/**
 * Class RelationResult
 * Объект для возврата результата выполнения методов
 * @property mixed $master
 * @property mixed $slave
 * @property ActiveRecord $relationLink
 * @property null|bool $success true: операция успешна, false: операция неуспешна, null: действие не производилось (не было необходимости)
 */
class RelationResult extends Component {
	public $master;
	public $slave;
	public ActiveRecord $relationLink;
	public ?bool $success = null;
}