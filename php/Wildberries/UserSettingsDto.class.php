<?php

namespace Stripmag\Marketplace\Wildberries;

use Stripmag\Marketplace\DTO\UserSettings;

class UserSettingsDto extends UserSettings
{
	public $feed;
	public $apiKey;
	public $enable;
	public $enableOrder;
	public $warehouseId;
	public $enableFbs;
	public $enableSendFbs;

	protected static $properties = [
		'feed' => 'Фид для выгрузки',
		'apiKey' => 'Ключ API',
		'enable' => 'Включить передачу остатков по API',
		'enableOrder' => 'Включить прием заказов по API'
	];
	protected static $json = [
		'warehouseId' => 'Склад',
		'enableFbs' => 'Автоматически формировать отгрузку',
		'enableSendFbs' => 'Автоматически отправлять отгрузку FBS',
		'pack' => 'Выберите тип упаковки для заказов'
	];
}
