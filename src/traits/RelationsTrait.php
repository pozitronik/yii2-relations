<?php
declare(strict_types = 1);

namespace pozitronik\relations\traits;

use pozitronik\helpers\ArrayHelper;
use pozitronik\relations\models\RelationResult;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\db\StaleObjectException;

/**
 * Trait Relations
 * Функции, общеприменимые ко всем таблицам связей.
 *
 * PHPStorm 2021.2 некорректно воспринимает объявления абстрактных методов в трейтах, поэтому они заменены на
 * объявления в PHPDoc
 * @method static findAll(mixed $condition)
 * @see ActiveRecord::findAll()
 * @method static findOne(mixed $condition)
 * @see ActiveRecord::findOne()
 */
trait RelationsTrait {

	/**
	 * Преобразует переданный параметр к единому виду
	 * @param int|string|ActiveRecord $storage
	 * @return int|string
	 * @throws Throwable
	 * @throws InvalidConfigException
	 */
	private static function extractKeyValue($storage) {
		if (is_numeric($storage)) return (int)$storage;
		if (is_object($storage)) return ArrayHelper::getValue($storage, 'primaryKey', new Exception("Класс {$storage->formName()} не имеет атрибута primaryKey"));
		return (string)$storage; //suppose it string field name
	}

	/**
	 * @return string
	 * @throws Throwable
	 */
	private static function getFirstAttributeName():string {
		/** @var ActiveRecord $link */
		$link = new self();
		return ArrayHelper::getValue($link->rules(), '0.0.0', new Exception('Не удалось получить атрибут для связи'));
	}

	/**
	 * @return string
	 * @throws Throwable
	 */
	private static function getSecondAttributeName():string {
		/** @var ActiveRecord $link */
		$link = new self();
		return ArrayHelper::getValue($link->rules(), '0.0.1', new Exception('Не удалось получить атрибут для связи'));
	}

	/**
	 * Находит и возвращает существующую связь к базовой модели
	 * @param ActiveRecord|int|string $master
	 * @return self[]
	 * @throws Throwable
	 */
	public static function currentLink($master):array {
		if (empty($master)) return [];
		return static::findAll([static::getFirstAttributeName() => static::extractKeyValue($master)]);
	}

	/**
	 * Находит и возвращает существующую связь от базовой модели
	 * @param ActiveRecord|int|string $slave
	 * @return self[]
	 * @throws Throwable
	 */
	public static function currentBackLink($slave):array {
		if (empty($slave)) return [];
		return static::findAll([static::getSecondAttributeName() => static::extractKeyValue($slave)]);
	}

	/**
	 * Возвращает все связи к базовой модели
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $master
	 * @return self[]
	 * @throws Throwable
	 */
	public static function currentLinks($master):array {
		$links = [[]];
		if (is_array($master)) {
			foreach ($master as $master_item) {
				$links[] = static::currentLink($master_item);
			}
		} else $links[] = static::currentLink($master);

		return array_merge(...$links);
	}

	/**
	 * Возвращает все связи от базовой модели
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $slave
	 * @return self[]
	 * @throws Throwable
	 */
	public static function currentBackLinks($slave):array {
		$links = [[]];
		if (is_array($slave)) {
			foreach ($slave as $slave_item) {
				$links[] = static::currentBackLink($slave_item);
			}
		} else $links[] = static::currentBackLink($slave);

		return array_merge(...$links);
	}

	/**
	 * Линкует в этом релейшене две модели. Модели могут быть заданы как через айдишники, так и моделью, и ещё тупо строкой
	 * @param ActiveRecord|int|string $master
	 * @param ActiveRecord|int|string $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @return RelationResult
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	public static function linkModel($master, $slave, bool $backLink = false):RelationResult {
		$result = new RelationResult(compact('master', 'slave'));
		if (empty($master) || empty($slave)) return $result;
		/*Пришёл запрос на связывание ActiveRecord-модели, ещё не имеющей primary key*/
		if (!$backLink && is_subclass_of($master, ActiveRecord::class, false) && $master->isNewRecord) {
			$master->on(BaseActiveRecord::EVENT_AFTER_INSERT, function($event) {//отложим связывание после сохранения
				return static::linkModel($event->data[0], $event->data[1], $event->data[2]);
			}, [$master, $slave, $backLink]);
		}
		/*Пришёл обратный запрос на связывание ActiveRecord-модели, ещё не имеющей primary key*/
		if ($backLink && is_subclass_of($slave, ActiveRecord::class, false) && $slave->isNewRecord) {
			$slave->on(BaseActiveRecord::EVENT_AFTER_INSERT, function($event) {//отложим связывание после сохранения
				return static::linkModel($event->data[0], $event->data[1], $event->data[2]);
			}, [$master, $slave, $backLink]);
		}

		$first_name = static::getFirstAttributeName();
		$second_name = static::getSecondAttributeName();
		$first_value = static::extractKeyValue($master);
		$second_value = static::extractKeyValue($slave);

		if (null !== $link = static::findOne([$first_name => $first_value, $second_name => $second_value])) {
			$result->success = true;//Связь уже существует
		} else {
			$link = new static();
			$link->$first_name = $first_value;
			$link->$second_name = $second_value;
			/** @var ActiveRecord $link */
			$result->success = $link->save();//Пытаемся создать связь
		}

		$result->relationLink = $link;

		return $result;
	}

	/**
	 * Линкует в этом релейшене две модели. Модели могут быть заданы как через айдишники, так и напрямую, в виде массивов или так.
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $master
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @return RelationResult[]
	 * @throws Throwable
	 * @noinspection NotOptimalIfConditionsInspection
	 */
	public static function linkModels($master, $slave, bool $backLink = false):array {
		$result = [];
		if (($backLink && empty($slave)) || (!$backLink && empty($master))) return $result;
		/*Удалим разницу (она может быть полной при очистке)*/
		static::dropDiffered($master, $slave, $backLink);

		if (empty($slave)) return $result;
		if (is_array($master)) {
			foreach ($master as $master_item) {
				if (is_array($slave)) {
					foreach ($slave as $slave_item) {
						$result[] = static::linkModel($master_item, $slave_item, $backLink);
					}
				} else $result[] = static::linkModel($master_item, $slave, $backLink);
			}
		} elseif (is_array($slave)) {
			foreach ($slave as $slave_item) {
				$result[] = static::linkModel($master, $slave_item, $backLink);
			}
		} else $result[] = static::linkModel($master, $slave, $backLink);
		return $result;
	}

	/**
	 * Вычисляет разницу между текущими и задаваемыми связями, удаляя те элементы, которые есть в текущей связи, но отсутствуют в устанавливаемой
	 * @param $master
	 * @param $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @noinspection TypeUnsafeArraySearchInspection
	 */
	private static function dropDiffered($master, $slave, bool $backLink = false):void {
		if ($backLink) {
			$currentItems = static::currentBackLinks($slave);
			$masterItemsKeys = [];
			$first_name = static::getFirstAttributeName();
			if (is_array($master)) {//вычисляем ключи моделей, к которым привязан линк
				foreach ($master as $value) $masterItemsKeys[] = static::extractKeyValue($value);
			} else {
				$masterItemsKeys[] = static::extractKeyValue($master);
			}
			foreach ($currentItems as $item) {//все
				if (!in_array($item->$first_name, $masterItemsKeys)) {
					$item::unlinkModel($item->$first_name, $slave);
				}
			}

		} else {
			$currentItems = static::currentLinks($master);
			$slaveItemsKeys = [];
			$second_name = static::getSecondAttributeName();
			if (is_array($slave)) {//вычисляем ключи линкованных моделей
				foreach ($slave as $value) $slaveItemsKeys[] = static::extractKeyValue($value);
			} else {
				$slaveItemsKeys[] = static::extractKeyValue($slave);
			}
			foreach ($currentItems as $item) {//все
				if (!in_array($item->$second_name, $slaveItemsKeys)) {
					$item::unlinkModel($master, $item->$second_name);
				}
			}
		}

	}

	/**
	 * Удаляет единичную связь в этом релейшене
	 * @param ActiveRecord|int|string $master
	 * @param ActiveRecord|int|string $slave
	 * @return RelationResult
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @throws StaleObjectException
	 */
	public static function unlinkModel($master, $slave):RelationResult {
		$result = new RelationResult(compact('master', 'slave'));
		if (empty($master) || empty($slave)) return $result;

		/** @var ActiveRecord $link */
		if (null !== $link = static::findOne([static::getFirstAttributeName() => static::extractKeyValue($master), static::getSecondAttributeName() => static::extractKeyValue($slave)])) {
			$result->relationLink = $link;
			$result->success = $link->delete();
		}
		return $result;
	}

	/**
	 * Удаляет связь между моделями в этом релейшене
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $master
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $slave
	 * @return RelationResult[]
	 * @throws Throwable
	 *
	 * Функция не будет работать с объектами, не имеющими атрибута/ключа id (даже если в качестве primaryKey указан другой атрибут).
	 * Такое поведение оставлено специально во избежание ошибок проектирования
	 *
	 * Передавать массивы строк/идентификаторов нельзя (только массив моделей)
	 * @noinspection NotOptimalIfConditionsInspection
	 */
	public static function unlinkModels($master, $slave):array {
		$result = [];
		if (empty($master) || empty($slave)) return $result;
		if (is_array($master)) {
			foreach ($master as $master_item) {
				if (is_array($slave)) {
					foreach ($slave as $slave_item) {
						$result[] = static::unlinkModel($master_item, $slave_item);
					}
				} else $result[] = static::unlinkModel($master_item, $slave);
			}
		} elseif (is_array($slave)) {
			foreach ($slave as $slave_item) {
				$result[] = static::unlinkModel($master, $slave_item);
			}
		} else $result[] = static::unlinkModel($master, $slave);
		return $result;
	}

	/**
	 * Удаляет все связи от модели в этом релейшене
	 * @param int|int[]|string|string[]|ActiveRecord|ActiveRecord[] $master
	 * @return RelationResult[]
	 * @throws Throwable
	 */
	public static function clearLinks($master):array {
		if (empty($master)) return [];

		if (is_array($master)) {
			$result = [[]];
			foreach ($master as $item) {
				$result[] = static::clearLinks($item);
			}
			return array_merge(...$result);
		}
		$result = [];
		foreach (static::findAll([static::getFirstAttributeName() => static::extractKeyValue($master)]) as $link) {
			/** @var ActiveRecord $link */
			$result[] = new RelationResult([
				'master' => $master,
				'relationLink' => $link,
				'result' => $link->delete()
			]);
		}
		return $result;
	}
}