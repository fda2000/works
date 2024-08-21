<?php

namespace Stripmag\Marketplace\DTO;

use BasePrimitive;
use PackType;
use Primitive;
use Utils\DtoProto;

class UserSettings extends DtoProto
{
	const YANDEX_SHOP = 'yandexShopId';

	public $name;

	protected static $protoJson = 'settings';

	/** Существует только один склад в настройках МП (нет вкладок) */
	protected $isSingleWarehouse = true;
	/** Существует только один вариант настроек МП (разные настройки в складах, если такие есть, все равно ведут в один ЛК) */
	protected $isSingleApiSettings = true;

	/** поле => обязательность заполнения (массив - в зависимости от выбора параметров) */
	protected static $types = [
		'name' => ['type' => 'text', 'required' => true, 'unique' => true],
		'feed' => ['type' => 'select', 'required' => ['enable']],
		'apiKey' => ['type' => 'text', 'required' => true, 'unique' => true],
		self::YANDEX_SHOP => ['type' => 'number', 'required' => true, 'title' => '<img src="/images/yandex-shop-settings.png" />'],
		'warehouseId' => ['type' => 'number', 'required' => ['enable'], 'unique' => true],
		'clientId' => ['type' => 'number', 'required' => true],
		'campaignId' => ['type' => 'number', 'required' => true, 'unique' => true],
		'oauthClientId' => ['type' => 'text', 'required' => true],
		'oauthToken' => ['type' => 'text', 'required' => true],
		'enable' => ['type' => 'checkbox', 'required' => false],
		'enableOrder' => ['type' => 'checkbox', 'required' => false],
		'enableFbs' => ['type' => 'checkbox', 'required' => false],
		'enableSendFbs' => ['type' => 'checkbox', 'required' => false],
		'pack' => ['type' => 'select', 'required' => true,
			'values' =>
				[
					PackType::T_BOX => 'Упаковка в коробку',
					PackType::T_PACKET => 'Упаковка в пакет'
				]
		]
	];

	public function isSingleWarehouse(): bool
	{
		return $this->isSingleWarehouse;
	}

	public function isSingleApiSettings(): bool
	{
		return $this->isSingleApiSettings || $this->isSingleWarehouse();
	}

	protected static function getProperties(): array
	{
		return ['name' => 'Название склада'] + parent::getProperties();
	}

	/**
	 * Типы смешиваем с классом-потомком, с приоритетом последнего
	 */
	private static function getTypes(): array
	{
		$items = self::$types;
		foreach (static::$types as $key => $item) {
			if ($item !== null) {
				$items[$key] = $item;
			} else {
				unset($items[$key]);
			}
		}

		return $items;
	}

	public function getSettingsFields(): array
	{
		$fields = static::getFields();
		$ret = array_intersect_key(self::getTypes(), $fields);
		foreach ($ret as $key => $val) {
			$ret[$key]['name'] = $fields[$key];
		}

		return $ret;
	}

	protected function getJsonPrimitive($key): BasePrimitive
	{
		$type = self::getTypes()[$key] ?? null;
		$typeName = $type['type'] ?? null;
		$primitive = parent::getJsonPrimitive($key);

		if ($typeName == 'number') {
			$primitive = Primitive::integer($key);
		}
		if ($typeName == 'checkbox') {
			$primitive = Primitive::boolean($key);
		}

		if (($type['required'] ?? false) === true) {
			$primitive->required();
		}

		return $primitive;
	}

	/** Возможность кастомных проверок при сохранении настроек */
	public function checkUpdate()
	{
	}
}
