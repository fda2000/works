<?php

namespace Stripmag\Marketplace;

use Administrator;
use Criteria;
use Expression;
use FileUtils;
use MarketBasketItem;
use MarketplaceUserAssortmentLink;
use MissingElementException;
use ObjectNotFoundException;
use OrderTransportLabel;
use OrderType;
use PackType;
use ShopOrder;
use Stripmag\Order\AssortmentErrorCode;
use Stripmag\Order\AutoTransferToProcess;
use Stripmag\Order\OrderBuilder2023;
use Stripmag\Order\Validator\BaseValidator;
use Stripmag\Service\Helper as ServiceHelper;
use Timestamp;
use WrongArgumentException;
use WrongStateException;

abstract class BaseOrderDriver extends BaseDriver
{
	// Как часто запускается скрипт в cron
	const NEXT_RUN_MODIFY = '+15 minutes';

	protected $orderTypesIds = null;
	protected $itemsMap = [];

	private $deliveryOrders = null;
	private $extOrders = null;

	/* @return MarketBasketItem[]
	 * @throws UserException
	 */
	abstract protected function buildAssortment(array $order): array;

	abstract protected function getOrderFields(array $order): array;

	protected function getAddress(array $fields)
	{
		return null;
	}

	protected function exportTrackNumber(ShopOrder $order)
	{
	}

	protected function getNextRun(Timestamp $current, int $nextCount = 1): Timestamp
	{
		while ($nextCount-- > 0) {
			$current = $current->spawn(self::NEXT_RUN_MODIFY);
		}
		return $current;
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 * @throws MissingElementException
	 */
	public function run()
	{
		if ($this->isValid()) {
			$this->importOrders();
			$this->importLabels();
			$this->exportTrackNumbers();
		}
	}

	public function processFbs()
	{
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 * @throws MissingElementException
	 */
	public function importOrders()
	{
		try {
			foreach ($this->api->getPackagingOrders() as $order) {
				try {
					$shopOrder = $this->importOrder($order);
					if ($shopOrder) {
						$this->addNote("Создан заказ {$shopOrder->getId()} из заказа МП {$shopOrder->getExternalId()}");
						$this->changeOrderStatus($order, $shopOrder);
					}
				} catch (UserException $e) {
					$this->addError($e->getMessage());
				}
			}
		} catch (UserException $e) {
			$this->addError($e->getMessage());
		}
	}

	/**
	 * @throws UserException
	 */
	protected function changeOrderStatus($order, ShopOrder $shopOrder) {
		$this->api->changeOrderStatus($order);
	}

	/**
	 * @throws WrongArgumentException
	 * @throws ObjectNotFoundException
	 * @throws WrongStateException
	 * @throws MissingElementException
	 * @throws UserException
	 */
	public function importOrder($order)
	{
		$shopOrder = null;
		if ($this->isValidPackagingOrder($order)) {
			$items = $this->buildAssortment($order);
			if ($items) {
				$shopOrder = $this->createOrder($this->getOrderFields($order), $items);
				if ($shopOrder) {
					/** @var Administrator $admin */
					$admin = Administrator::dao()->getById(Administrator::ADMIN_ROBOT);
					$admin->getLogHelper()->logPlaceOfOrder($shopOrder, 'Marketplace API.');
					$autoTransferToProcess = new AutoTransferToProcess($shopOrder);
					$autoTransferToProcess->run();
				}
			}
		}

		return $shopOrder;
	}

	protected function isValidPackagingOrder($order): bool
	{
		return true;
	}

	/**
	 * @throws WrongArgumentException
	 * @throws ObjectNotFoundException
	 */
	public function importLabels()
	{
		try {
			foreach ($this->getDeliveryOrders() as $order) {
				try {
					if (!$order->getTransportLabel()) {
						$this->fillLabel($order);
					}
				} catch (UserException $e) {
					$this->addError($e->getMessage());
				}
			}
		} catch (UserException $e) {
			$this->addError($e->getMessage());
		}
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 */
	private function exportTrackNumbers()
	{
		try {
			foreach ($this->getDeliveryOrders() as $order) {
				try {
					$this->exportTrackNumber($order);
				} catch (UserException $e) {
					$this->addError($e->getMessage());
				}
			}
		} catch (UserException $e) {
			$this->addError($e->getMessage());
		}
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	protected function fillLabel(ShopOrder $order)
	{
		$content = $this->getPackageLabel($order);
		if ($content === false) {
			$this->addInfo("Этикетка для заказа {$order->getId()} ({$order->getExternalId()}) пока не готова");
			return;
		}
		if (!$content) {
			throw new UserException('extOrderId=' . $order->getExternalId() . ' получена пустая этикетка');
		}

		$tmpFile = FileUtils::makeTempFile();
		if (!file_put_contents($tmpFile, $content)) {
			throw new UserException('extOrderId=' . $order->getExternalId() . ' ошибка записи файла');
		}

		$this->storeLabel($order, $tmpFile);
		$this->addNote("Скачана этикетка для заказа {$order->getId()} ({$order->getExternalId()})");
	}

	/**
	 * @throws UserException
	 */
	protected function getPackageLabel(ShopOrder $order)
	{
		return $this->api->getPackageLabel($order->getExternalId());
	}

	/**
	 * @throws UserException
	 */
	private function storeLabel(ShopOrder $order, $tmpFile)
	{
		if ($order->getTransportLabel()) {
			throw new UserException('orderId=' . $order->getId() . ' у заказа уже есть этикетка');
		}

		$label = OrderTransportLabel::create()
			->setCustom(true)
			->setMimeType(mime_content_type($tmpFile))
			->setOrder($order);

		OrderTransportLabel::dao()->store($label, $tmpFile);
	}

	/**
	 * @param array $fields
	 * @param $items MarketBasketItem[]
	 * @return ShopOrder | null
	 * @throws MissingElementException
	 * @throws ObjectNotFoundException
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 */
	protected function createOrder(array $fields, array $items)
	{
		if ($this->settings->getCompany()->isAllowMarketplacePack()) {
			$packId = $this->settings->getSettingsDTO()->getValue('pack');
			if ($packId) {
				$fields['packTypeId'] = $packId;
			}
		}

		$this->validateOrderData($fields, $items);

		$extId = $fields['extId'];
		$orderType = OrderType::dao()->getById($fields['orderTypeId']);

        $servicePackingId = ServiceHelper::convertPackTypeToService($fields['packTypeId'] ?? null);

        try {
			$data = [
                'servicePackingId' => $servicePackingId,
				'costType' => $this->settings->getCompany()->getDiscount() == 10 ? 'costopt' : 'costwebshop', //добавляем тип цены для заказа
				'externalId' => $extId,
				'marketplaceShipmentDate' => $fields['marketplaceShipmentDate'] ?? null,
				'userComment' => $fields['userComment'] ?? '',
				'historyComment' => 'api marketplace order create',
				'adminComment' => $fields['adminComment'] ?? 'Заказ из маркетплейса',
				'itemList' => $items,
			];

            if ($fields['test'] ?? false) {
                $data['test'] = true;
            }

            if ($fields['deliveryTypeId'] ?? null) {
                $data['delivery'] = $fields['deliveryTypeId'];
                $address = $this->getAddress($fields);
                if ($address) {
                    $data['address'] = $address;
                }
            }

			$validator = new BaseValidator($this->settings->getCompany()->getRegularUser(), $orderType);
			$validatedData = $validator->validate($data, 'marketplaceApi');
		} catch (WrongArgumentException $e) {
			throw new UserException('Ошибка при создании заказа: ' . $e->getMessage());
		}

		$itemErrors = $validator->getItemErrors();
		if ($itemErrors) {
			$itemErrors = $this->addOrderErrorMessages($itemErrors);
			throw new UserException('Ошибки при создании заказа: ' . json_encode($itemErrors, JSON_UNESCAPED_UNICODE));
		}

		$builder = OrderBuilder2023::create($this->settings->getCompany()->getRegularUser(), $orderType);
		return $builder->build($validatedData);
	}

	/**
	 * @throws WrongStateException
	 * @throws UserException
	 * @throws ObjectNotFoundException
	 */
	protected function buildAssortmentItems(array $items): array
	{
		$items = $this->getQuantities($items);
		if ($this->isEmptyLinks()) {
			$this->setLinksByOrderItems(array_keys($items));
		}

		$assortment = [];
		foreach ($items as $offerId => $quantity) {
			$link = null;
			if (isset($this->itemsMap['productArticle'])) {
				$link = $this->getAssortmentLinkByArticle($offerId);
			}
			if (isset($this->itemsMap['mpBarcode'])) {
				$link = $this->getAssortmentLinkByBarcode($offerId);
			}

			$assortmentId = $link ? $link->getAssortmentId() : null;
			if (!$assortmentId) {
				throw new UserException(
					'offerId=' . $offerId . ' не найден в привязках маркетплейса'
				);
			}

			if (isset($assortment[$assortmentId])) {
				$assortment[$assortmentId]['quantity'] += $quantity;
			} else {
				$assortment[$assortmentId] = [
					'assortmentId' => $assortmentId,
					'quantity' => $quantity,
				];
			}
		}

		return $assortment;
	}

	/**
	 * @throws WrongStateException
	 */
	private function getQuantities(array $items): array
	{
		$map = [];
		foreach ($items as $item) {
			$offerId = $quantity = null;
			foreach ($this->itemsMap as $type => $field) {
				$value = $item[$field] ?? null;
				if (!$value) {
					throw new WrongStateException("No $field item");
				}

				if ($type == 'quantity') {
					$quantity = $value;
					if ($quantity <= 0) {
						throw new WrongStateException('Wrong quantity item');
					}
				} else {
					$offerId = $value;
				}
			}

			if (isset($map[$offerId])) {
				$map[$offerId] += $quantity;
			} else {
				$map[$offerId] = $quantity;
			}
		}

		return $map;
	}

	/**
	 * @throws ObjectNotFoundException
	 */
	private function setLinksByOrderItems(array $items)
	{
		$criteria = Criteria::create(MarketplaceUserAssortmentLink::dao())
			->add(Expression::eq('user', $this->settings->getCompany()->getRegularUserId()))
			->add(Expression::eq('marketplace', $this->settings->getMarketplaceId()));

		if (isset($this->itemsMap['productArticle'])) {
			$criteria->add(Expression::in('productArticle', $items));
		}
		if (isset($this->itemsMap['mpBarcode'])) {
			$criteria->add(Expression::in('mpBarcode', $items));
		}

		$this->setAssortmentLinks($criteria->getList());
	}

	private function addOrderErrorMessages(array $itemErrors): array
	{
		foreach ($itemErrors as $key => $error) {
			if (isset($error['errorCode'])) {
				try {
					/** @var AssortmentErrorCode $code */
					$code = AssortmentErrorCode::create($error['errorCode']);
					$itemErrors[$key]['errorMessage'] = $code->getLabel();
				} catch (MissingElementException $e) {
				}
			}
		}
		return $itemErrors;
	}

	/**
	 * @throws MissingElementException
	 * @throws ObjectNotFoundException
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	private function validateOrderData(array $fields, array $items)
	{
		$extId = $fields['extId'] ?? null;
		if (!$extId) {
			throw new UserException('Создание заказа: не задан extId');
		}
		if (!$items) {
			throw new UserException('Создание заказа: список товаров пустой');
		}

		if (ShopOrder::dao()->getOrderIdByExtId($extId, $this->settings->getCompany()->getRegularUserId(), $this->orderTypesIds)) {
			throw new UserException('Создание заказа: extId=' . $extId . ' уже существует');
		}
	}

	/**
	 * @param MarketBasketItem[] $items
	 * @return MarketBasketItem[]
	 */
	private function addPack(array $items, int $packTypeId): array
	{
		$packType = PackType::create($packTypeId);
		$packAssortIds = PackType::getAssortmentMapIdList();
		$packAssortmentId = $packAssortIds[$packType->getId()] ?? null;
		if ($packAssortmentId) {
			$items[$packAssortmentId] = [
				'assortmentId' => $packAssortmentId,
				'quantity' => 1,
			];
		}
		return $items;
	}

	protected function getTrackNumber(ShopOrder $order)
	{
		$postOrder = $order->getPostOrder();
		if (!$postOrder) {
			return null;
		}

		return $postOrder->getPostCode();
	}

	/**
	 * @return ShopOrder[]
	 * @throws ObjectNotFoundException
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	protected function getDeliveryOrders(): array
	{
		if ($this->extOrders === null) {
			$this->deliveryOrders = $this->api->getDeliveryOrdersMap();
			if ($this->deliveryOrders === false) {
				$this->extOrders = $this->getOurOrdersNoLabel();
				$ids = array_map(function ($item) {
					return $item->getExternalId();
				}, $this->extOrders);
				$this->deliveryOrders = array_fill_keys($ids, []);
			} else {
				$userId = $this->settings->getCompany()->getRegularUserId();
				$this->extOrders = ShopOrder::dao()->getOrdersByExtIds(array_keys($this->deliveryOrders), $userId, $this->orderTypesIds);
			}
		}

		return $this->extOrders;
	}

	protected function getMpResponseOrderData(ShopOrder $order): array
	{
		return $this->deliveryOrders[$order->getExternalId()];
	}

	/**
	 * @return ShopOrder[]
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 */
	private function getOurOrdersNoLabel(): array
	{
		$userId = $this->settings->getCompany()->getRegularUserId();
		return ShopOrder::dao()->getWorkOrdersNoLabel($userId, $this->orderTypesIds);
	}
}
