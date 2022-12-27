<?php
declare(strict_types = 1);

namespace pozitronik\relations\traits;

use pozitronik\helpers\ArrayHelper;
use pozitronik\relations\models\RelationException;
use Throwable;
use Yii;
use yii\base\Event;
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
	private static ?bool $_modeAfterPrimary = null;

	/**
	 * Получение режима операций со связанными моделями:
	 * true: операции будут выполняться после сохранения изменений основной модели,
	 * false: в момент изменения свойства.
	 * Настройка задаётся глобально в конфигурации приложения в components.relations.afterPrimaryMode, либо может быть
	 * переопределена непосредственно для каждой операции через соответствующий параметр.
	 * @param bool|null $mode null: получить глобальную конфигурацию, иначе переопределённую
	 * @return bool
	 * @throws Throwable
	 */
	public static function getAfterPrimaryMode(?bool $mode = null):bool {
		return $mode??(static::$_modeAfterPrimary ??= ArrayHelper::getValue(Yii::$app->components, 'relations.afterPrimaryMode', false));
	}

	/**
	 * Преобразует переданный параметр к единому виду
	 * @param int|string|ActiveRecord $storage
	 * @return int|string
	 * @throws Throwable
	 * @throws InvalidConfigException
	 */
	private static function extractKeyValue(int|string|ActiveRecord $storage) {
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
		$link = new static();
		return ArrayHelper::getValue($link->rules(), '0.0.0', new Exception('Не удалось получить атрибут для связи'));
	}

	/**
	 * @return string
	 * @throws Throwable
	 */
	private static function getSecondAttributeName():string {
		/** @var ActiveRecord $link */
		$link = new static();
		return ArrayHelper::getValue($link->rules(), '0.0.1', new Exception('Не удалось получить атрибут для связи'));
	}

	/**
	 * Находит и возвращает существующую связь к базовой модели
	 * @param int|string|ActiveRecord $master
	 * @return static[]
	 * @throws Throwable
	 */
	public static function currentLink(int|string|ActiveRecord $master):array {
		if (empty($master)) return [];
		return static::findAll([static::getFirstAttributeName() => static::extractKeyValue($master)]);
	}

	/**
	 * Находит и возвращает существующую связь от базовой модели
	 * @param int|string|ActiveRecord $slave
	 * @return static[]
	 * @throws Throwable
	 */
	public static function currentBackLink(int|string|ActiveRecord $slave):array {
		if (empty($slave)) return [];
		return static::findAll([static::getSecondAttributeName() => static::extractKeyValue($slave)]);
	}

	/**
	 * Возвращает все связи к базовой модели
	 * @param int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $master
	 * @return static[]
	 * @throws Throwable
	 */
	public static function currentLinks(array|int|string|ActiveRecord $master):array {
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
	 * @param int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $slave
	 * @return static[]
	 * @throws Throwable
	 */
	public static function currentBackLinks(array|int|string|ActiveRecord $slave):array {
		$links = [[]];
		if (is_array($slave)) {
			foreach ($slave as $slave_item) {
				$links[] = static::currentBackLink($slave_item);
			}
		} else $links[] = static::currentBackLink($slave);

		return array_merge(...$links);
	}

	/**
	 * @param int|string|ActiveRecord $master
	 * @param int|string|ActiveRecord $slave
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	private static function setLink(int|string|ActiveRecord $master, int|string|ActiveRecord $slave):void {
		$first_name = static::getFirstAttributeName();
		$second_name = static::getSecondAttributeName();
		$first_value = static::extractKeyValue($master);
		$second_value = static::extractKeyValue($slave);

		/*Если связь уже существует - не сохраняем (чтобы избежать валидации и неизбежной ошибки уникальной записи)*/
		if (null === static::findOne([$first_name => $first_value, $second_name => $second_value])) {
			/** @var ActiveRecord $link */
			$link = new static();
			$link->$first_name = $first_value;
			$link->$second_name = $second_value;
			if (false === $link->save()) {
				throw new RelationException("Relation save error: ", $link);
			}
		}
	}

	/**
	 * @param self|ActiveRecord $link
	 * @throws RelationException
	 * @throws Throwable
	 * @throws StaleObjectException
	 */
	private static function deleteLink(ActiveRecord|self $link):void {
		if (false === $link->delete()) {
			throw new RelationException("Relation delete error: ", $link);
		}
	}

	/**
	 * Линкует в этом релейшене две модели. Модели могут быть заданы как через айдишники, так и моделью, и ещё тупо строкой
	 * @param null|int|string|ActiveRecord $master
	 * @param null|int|string|ActiveRecord $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @param null|bool $linkAfterPrimary true: связывание произойдёт только после сохранения основной модели, false: в момент присвоения свойства, null: глобальная настройка
	 * Для новых ActiveRecord моделей связывание всегда происходит после сохранения.
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	public static function linkModel(null|int|string|ActiveRecord $master, null|int|string|ActiveRecord $slave, bool $backLink = false, ?bool $linkAfterPrimary = null):void {
		if (empty($master) || empty($slave)) return;
		/*Определяем модель, являющуюся в этой связи основной*/
		$primaryItem = $backLink?$slave:$master;
		/*Связывание отложено, либо пришёл запрос на связывание ActiveRecord-модели, ещё не имеющей primary key*/
		$linkAfterPrimary = static::getAfterPrimaryMode($linkAfterPrimary) || (is_subclass_of($primaryItem, ActiveRecord::class, false) && $primaryItem->isNewRecord);

		if ($linkAfterPrimary) {//Связывание произойдёт после сохранения основной модели
			$primaryItem->on($primaryItem->isNewRecord?BaseActiveRecord::EVENT_AFTER_INSERT:BaseActiveRecord::EVENT_AFTER_UPDATE, [__CLASS__, 'setLinkHandler'], [$master, $slave]);//see setLinkHandler()
		} else {
			static::setLink($master, $slave);
		}
	}

	/**
	 * Линкует в этом релейшене две модели. Модели могут быть заданы как через айдишники, так и напрямую, в виде массивов или так.
	 * @param null|int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $master
	 * @param null|int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @param null|bool $linkAfterPrimary true: связывание произойдёт только после сохранения основной модели, false: в момент присвоения свойства, null: глобальная настройка
	 * @throws Throwable
	 */
	public static function linkModels(null|array|int|string|ActiveRecord $master, null|array|int|string|ActiveRecord $slave, bool $backLink = false, ?bool $linkAfterPrimary = null):void {
		if (($backLink && empty($slave)) || (!$backLink && empty($master))) return;
		/*Удалим разницу (она может быть полной при очистке)*/
		static::dropDiffered($master, $slave, $backLink, $linkAfterPrimary);
		if (empty($slave)) return;

		if (is_array($master)) {
			foreach ($master as $master_item) {
				if (is_array($slave)) {
					foreach ($slave as $slave_item) {
						static::linkModel($master_item, $slave_item, $backLink, $linkAfterPrimary);
					}
				} else static::linkModel($master_item, $slave, $backLink, $linkAfterPrimary);
			}
		} elseif (is_array($slave)) {
			foreach ($slave as $slave_item) {
				static::linkModel($master, $slave_item, $backLink, $linkAfterPrimary);
			}
		} else static::linkModel($master, $slave, $backLink, $linkAfterPrimary);
	}

	/**
	 * Вычисляет разницу между текущими и задаваемыми связями, удаляя те элементы, которые есть в текущей связи, но отсутствуют в устанавливаемой
	 * @param null|array|int|string|ActiveRecord $master
	 * @param null|array|int|string|ActiveRecord $slave
	 * @param bool $backLink Если связь задана в "обратную сторону", т.е. основная модель присоединяется к вторичной.
	 * @param null|bool $dropAfterPrimary true: удаление произойдёт только после сохранения основной модели, false: в момент изменения свойства, null: глобальная настройка
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @noinspection TypeUnsafeArraySearchInspection
	 */
	private static function dropDiffered(null|array|int|string|ActiveRecord $master, null|array|int|string|ActiveRecord $slave, bool $backLink = false, ?bool $dropAfterPrimary = false):void {
		if (empty($master) || empty($slave)) return;
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
					$item::unlinkModel($item->$first_name, $slave, $dropAfterPrimary);
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
					$item::unlinkModel($master, $item->$second_name, $dropAfterPrimary);
				}
			}
		}

	}

	/**
	 * Удаляет единичную связь в этом релейшене
	 * @param null|int|string|ActiveRecord $master
	 * @param null|int|string|ActiveRecord $slave
	 * @param null|bool $clearAfterPrimary true: удаление произойдёт только после сохранения основной модели, false: в момент изменения свойства, null: глобальная настройка
	 * @throws Throwable
	 */
	public static function unlinkModel(null|int|string|ActiveRecord $master, null|int|string|ActiveRecord $slave, ?bool $clearAfterPrimary = null):void {
		if (empty($master) || empty($slave)) return;

		if (null !== $link = static::findOne([static::getFirstAttributeName() => static::extractKeyValue($master), static::getSecondAttributeName() => static::extractKeyValue($slave)])) {
			/** @var ActiveRecord $link */
			if (static::getAfterPrimaryMode($clearAfterPrimary)) {
				$master->on(BaseActiveRecord::EVENT_AFTER_UPDATE, [__CLASS__, 'deleteLinkHandler'], [$link]);//see deleteLinkHandler()
			} else {
				static::deleteLink($link);
			}
		}
	}

	/**
	 * Удаляет связь между моделями в этом релейшене
	 * @param null|int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $master
	 * @param null|int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $slave
	 * @param null|bool $clearAfterPrimary true: удаление произойдёт только после сохранения основной модели, false: в момент присвоения свойства, null: глобальная настройка
	 * @throws Throwable
	 *
	 * Функция не будет работать с объектами, не имеющими атрибута/ключа id (даже если в качестве primaryKey указан другой атрибут).
	 * Такое поведение оставлено специально во избежание ошибок проектирования
	 *
	 * Передавать массивы строк/идентификаторов нельзя (только массив моделей)
	 */
	public static function unlinkModels(null|array|int|string|ActiveRecord $master, null|array|int|string|ActiveRecord $slave, ?bool $clearAfterPrimary = null):void {
		if (empty($master) || empty($slave)) return;
		if (is_array($master)) {
			foreach ($master as $master_item) {
				if (is_array($slave)) {
					foreach ($slave as $slave_item) {
						static::unlinkModel($master_item, $slave_item, $clearAfterPrimary);
					}
				} else static::unlinkModel($master_item, $slave, $clearAfterPrimary);
			}
		} elseif (is_array($slave)) {
			foreach ($slave as $slave_item) {
				static::unlinkModel($master, $slave_item, $clearAfterPrimary);
			}
		} else static::unlinkModel($master, $slave, $clearAfterPrimary);
	}

	/**
	 * Удаляет все связи от модели в этом релейшене
	 * @param int|string|ActiveRecord|ActiveRecord[]|int[]|string[] $master
	 * @param null|bool $clearAfterPrimary true: удаление произойдёт только после сохранения основной модели, false: в момент изменения свойства, null: глобальная настройка
	 * @throws Throwable
	 */
	public static function clearLinks(null|array|int|string|ActiveRecord $master, ?bool $clearAfterPrimary = null):void {
		if (empty($master)) return;

		if (is_array($master)) {
			foreach ($master as $item) static::clearLinks($item, $clearAfterPrimary);
		}

		foreach (static::findAll([static::getFirstAttributeName() => static::extractKeyValue($master)]) as $link) {
			/** @var ActiveRecord $link */
			if (static::getAfterPrimaryMode($clearAfterPrimary)) {
				$master->on(BaseActiveRecord::EVENT_AFTER_UPDATE, [__CLASS__, 'deleteLinkHandler'], [$link]);//see deleteLinkHandler()
			} else {
				static::deleteLink($link);
			}
		}
	}

	/**
	 * Метод нужен для однократного срабатывания присвоения связи после сохранения основной модели:
	 * событие удаляется после первого вызова. Если этого не делать, то повторные сохранения основной модели
	 * будут вызывать всю цепочку присвоений.
	 * @param Event $event
	 * @return void
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	protected static function setLinkHandler(Event $event):void {
		static::setLink($event->data[0], $event->data[1]);
		$event->sender->off($event->name, [__CLASS__, 'setLinkHandler']);
	}

	/**
	 * Метод нужен для однократного срабатывания удаления связи после сохранения основной модели.
	 * @param Event $event
	 * @return void
	 * @throws RelationException
	 * @throws StaleObjectException
	 * @throws Throwable
	 */
	protected static function deleteLinkHandler(Event $event):void {
		static::deleteLink($event->data[0]);
		$event->sender->off($event->name, [__CLASS__, 'deleteLinkHandler']);
	}
}