<?php

namespace Stripmag\Marketplace\Wildberries;

use Administrator;
use AssemblyPull;
use AssemblyPullHelper;
use DsDaoUtils;
use ErrorUtils;
use Exception;
use MarketplaceUserApiSettings;
use MissingElementException;
use ObjectNotFoundException;
use OrderType;
use PackType;
use ShopOrder;
use Stripmag\Marketplace\ApiException;
use Stripmag\Marketplace\SupplyOrderDriver;
use Stripmag\Marketplace\UserException;
use Timestamp;
use TimestampRange;
use WrongArgumentException;
use WrongStateException;

class OrderDriver extends SupplyOrderDriver
{
	const FORCED_PROCESS_FBS = false;
	protected $orderTypesIds = [OrderType::WILDBERRIES_FBS];

	/**
	 * Интервал, в котором происходит отправка FBS
	 */
	public function getSendFbsRange(): TimestampRange
	{
		return TimestampRange::create(
			Timestamp::create('12:30:00'),
			Timestamp::create('12:50:00')
		);
	}

	/**
	 * @throws ApiException
	 * @throws UserException
	 * @throws WrongStateException
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 */
	protected function createOrder(array $fields, array $items)
	{
		$userId = $this->settings->getCompany()->getRegularUserId();
		$order = current(ShopOrder::dao()->getOrdersByExtIds([$fields['extId']], $userId, $this->orderTypesIds));
		if ($order) {
			$this->changeOrderStatus([], $order);
			return null;
		}

		return parent::createOrder($fields, $items);
	}

	/**
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 */
	public function __construct(MarketplaceUserApiSettings $settings)
	{
		parent::__construct($settings);
		$dto = $this->settings->getSettingsDTO();
		$this->api = new Api($dto->getValue('apiKey'), $dto->getValue('warehouseId'));
		if ($this->isFbsEnabled()) {
			$this->api->enableLog($this->getLogName());
		}
	}

	protected function getOrderFields(array $order): array
	{
		return [
			'extId' => $order['id'],
			'packTypeId' => PackType::T_BOX,
			'orderTypeId' => current($this->orderTypesIds)
		];
	}

	/**
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 */
	protected function isValidPackagingOrder($order): bool
	{
		$dto = $this->settings->getSettingsDTO();
		return (!$dto->getValue('warehouseId') || $order['warehouseId'] == $dto->getValue('warehouseId'));
	}

	/**
	 * @throws UserException
	 */
	protected function buildAssortment(array $order): array
	{
		$barcode = $order['skus'][0] ?? null;
		if (!$barcode) {
			throw new UserException(
				'barcode не найден в заказе orderId=' . $order['id']
			);
		}

		$link = $this->getAssortmentLinkByBarcode($barcode);
		$assortmentId = $link ? $link->getAssortmentId() : null;
		if (!$assortmentId) {
			throw new UserException(
				'barcode=' . $barcode . ' не найден в привязках маркетплейса'
			);
		}

		$result[$assortmentId] = [
			'assortmentId' => $assortmentId,
			'quantity' => 1,
		];
		return $result;
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 * @throws ApiException
	 * @throws ObjectNotFoundException
	 * @throws Exception
	 */
	protected function changeOrderStatus($order, ShopOrder $shopOrder)
	{
		$orderError = "Заказ {$shopOrder->getId()} ({$shopOrder->getExternalId()} не добавлен в отгрузку, потому что";
		$robot = Administrator::dao()->getRobot();
		$helper = AssemblyPullHelper::create()->setAdmin($robot);
		if (!$this->isFbsEnabled()) {
			return;
		}
		if ($shopOrder->getAssemblyPullId()) {
			return;
		}
		if (!$shopOrder->readyForPickUp(Timestamp::makeNow())) {
			return;
		}
		if (!$helper->getStopTime()) {
			return;
		}

		$currentPull = $helper->getAssemblyPullNew($shopOrder);
		if ($currentPull) {
			$supplyId = $currentPull->getExternalId();
			if (!$supplyId) {
				$this->addError("$orderError у нее пустой внешний код");
				return;
			}

			$supply = $this->api->getSupplyById($supplyId);
			$supplyId = $this->getValidSupplyId($supply);
			if (!$supplyId) {
				$this->addError("$orderError поставка с внешним кодом не найдена в МП");
				return;
			}

		} else {
			$supplyId = $this->buildNowSupplyId();
			if (!$supplyId) {
				$this->addError("$orderError что-то не так с поставкой в МП");
				return;
			}

			if (AssemblyPull::dao()->existExternalPull($shopOrder, $supplyId)) {
				$this->addError("$orderError уже существует отгрузка с таким внешним кодом");
				return;
			}
		}

		DsDaoUtils::doInTransaction(AssemblyPull::dao(), function () use ($helper, $shopOrder, $supplyId) {
			try {
				$helper->setExternalId($supplyId)->addOrder($shopOrder);
			} catch (WrongStateException $e) {
				throw new UserException($e->getMessage());
			}

			$this->api->addToSupply($shopOrder->getExternalId(), $supplyId);
			$this->addNote("Заказ {$shopOrder->getExternalId()} добавлен в поставку МП $supplyId");
			$this->addNote("Заказ {$shopOrder->getId()} добавлен в отгрузку FBS {$shopOrder->getAssemblyPullId()}");
		});
	}

	/**
	 * @throws ApiException
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	private function buildNowSupplyId()
	{
		$name = Timestamp::makeNow()->toFormatString('d-m-Y');
		$supply = $this->api->findSupplyByName($name);
		if (!$supply) {
			$supply = $this->api->createSupply($name);
			$this->addNote("Создана поставка в МП {$supply['id']}");
		}
		return $this->getValidSupplyId($supply);
	}

	private function getValidSupplyId($supply)
	{
		return $supply && !$supply['done'] ? $supply['id'] : null;
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongStateException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 * @throws UserException
	 */
	public function deliverySupply()
	{
		$currentPull = $this->getCurrentPull();
		$externalId = $currentPull->getExternalId();
		$this->api->deliverySupply($externalId);
		$this->addNote("Поставка МП $externalId переведена в статус В доставке");
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function getSupplyOrderIds($supplyId): array
	{
		return array_map(function ($order) {
			return $order['id'];
		}, $this->api->getSupplyOrders($supplyId));
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongStateException
	 */
	protected function getCurrentPull($externalId = null): AssemblyPull
	{
		$currentPull = parent::getCurrentPull($externalId);
		if (!$currentPull) {
			throw new WrongStateException('Не найдена отгрузка в статусе Новая', 404);
		}
		if (!$currentPull->getExternalId()) {
			throw new WrongStateException('У отгрузки не заполнен внешний код');
		}

		return $currentPull;
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws Exception
	 */
	public function processFbs()
	{
		try {
			$startRange = $this->getSendFbsRange();
			/** @var Timestamp $firstStart */
			$firstStart = $startRange->getStart();
			$secondStart = $this->getNextRun($firstStart);

			if (!$startRange->contains($this->startScript) && !self::FORCED_PROCESS_FBS) {
				return;
			}
			if (!$this->settings->getSettingsDTO()->getValue('enableSendFbs')) {
				return;
			}

			try {
				$this->getCurrentPull();
			} catch (WrongStateException $e) {
				//Если отгрузки нет - значит нет заказов на сегодня
				if ($e->getCode() == 404) {
					return;
				}
				throw new UserException($e->getMessage());
			}

			if ($this->startScript->toStamp() < $secondStart->toStamp() || self::FORCED_PROCESS_FBS) {
				//Только при первом запуске
				if ($this->checkSupplies() !== true) {
					throw new UserException('Не совпадают заказы в поставке МП и в FBS-отгрузке');
				}
				$this->deliverySupply();
			}

			if ($this->getCurrentPull()->getAssemblyPullFilesNames()) {
				//Этикетка уже получена
				return;
			}

			try {
				//две попытки запуска
				$this->loadSupplyLabelAndChangeStatus();
				$this->sendFbsResult(false);
			} catch (UserException $e) {
				if ($this->startScript->toStamp() >= $secondStart->toStamp()) {
					//Только при втором запуске
					//SupplyNotClosed - значит в прошлый раз не получилось deliverySupply, не отсылаем ошибку еще раз
					if ($e->getCode() != 'SupplyNotClosed') {
						throw $e;
					}
				}
			}

		} catch (UserException $e) {
			$this->sendFbsResult($e->getMessage());
			$this->addError($e->getMessage());
		} catch (WrongStateException $e) {
			$this->sendFbsResult($e->getMessage());
			$this->addError($e->getMessage());
		} catch (Exception $e) {
			ErrorUtils::doNotice($e);
			$this->sendFbsResult(true);
			throw $e;
		}
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	public function getSupplyBarcode($supplyId)
	{
		return $this->api->getSupplyBarcode($supplyId);
	}
}
