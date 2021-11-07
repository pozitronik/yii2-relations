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
 * @property bool $success
 */
class RelationResult extends Component {
	public $master;
	public $slave;
	public ActiveRecord $relationLink;
	public bool $success = false;
}