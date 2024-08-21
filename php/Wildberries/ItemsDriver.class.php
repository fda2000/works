<?php

namespace Stripmag\Marketplace\Wildberries;

use Criteria;
use Exception;
use Expression;
use MarketplaceUserApiSettings;
use MissingElementException;
use ObjectNotFoundException;
use ProductAssortment;
use ProductInSale;
use Projection;
use Stripmag\Marketplace\ApiException;
use Stripmag\Marketplace\BaseItemsDriver;
use Stripmag\Marketplace\UserException;
use WrongArgumentException;

class ItemsDriver extends BaseItemsDriver
{
	const STOCK_UPDATE_LIMIT = 1000;
	const NO_ERROR_TEXT = 'No goods for process';

	/**
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 */
	public function __construct(MarketplaceUserApiSettings $settings)
	{
		parent::__construct($settings);
		$dto = $this->settings->getSettingsDTO();
		$this->api = new Api($dto->getValue('apiKey'), $dto->getValue('warehouseId'));
		$this->api->enableLog($this->getLogName());
	}

	/**
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 */
	protected function isValid(): bool
	{
		$dto = $this->settings->getSettingsDTO();
		return $this->feed
			&& $this->feed->getType()->isWildberries()
			&& $dto->getValue('warehouseId') > 0
			&& parent::isValid();
	}

	/**
	 * @throws WrongArgumentException
	 * @throws ObjectNotFoundException
	 */
	public function updatePrice()
	{

		$prices = $pricesMap = [];
		foreach ($this->feedResult as $key => $line) {
			if ($key > 0 && isset($line[0]) && isset($line[1]) && isset($line[2])) {
				$link = $this->getAssortmentLinkByAssortment($line[0]);
				$sku = $link ? (int)$link->getMpSku() : null;
				$rest = (int)$line[1];
				$price = ceil($line[2] ?? 0);
				if ($sku > 0 && $rest > 0 && $price > 0) {
					$pricesMap[$link->getAssortmentId()] = [
						'nmId' => $sku,
						'price' => $price
					];
				}
			}
		}

		$disabled = array_flip($this->getNotInSaleAids(array_keys($pricesMap)));
		foreach ($pricesMap as $aId => $item) {
			if (!isset($disabled[$aId])) {
				$prices[$item['nmId']] = $item;
			}
		}

		foreach (array_chunk($prices, 1000, true) as $part) {
			try {
				$this->api->apiPricesUpdate(array_values($part));
			} catch (ApiException $e) {
				$error = $e->getMessage();
				if (strpos($error, self::NO_ERROR_TEXT) === false) {
					$this->processPricesApiException($e, $part);
				}
			} catch (UserException $e) {
				$error = $e->getMessage();
				if (strpos($error, self::NO_ERROR_TEXT) === false) {
					$this->addError($error);
				}
			}
		}
	}

	/**
	 * @throws ObjectNotFoundException
	 */
	private function getNotInSaleAids($aIds)
	{
		if (empty($aIds)) {
			return [];
		}

		$criteria = Criteria::create(ProductAssortment::dao())
			->addProjection(Projection::property('id', 'id'))
			->add(Expression::notEq('product.inSale', ProductInSale::YES))
			->add(Expression::in('id', $aIds));

		return $criteria->getPropertyList();
	}

	public function processPricesApiException(ApiException $e, $part)
	{
		if (strpos($e->getMessage(),
				'все номенклатуры с ценами из списка уже загружены, новая загрузка не создана') !== false) {
			return;
		}

		$count = count($part);
		$body = $e->getResponse()->getBody();
		$res = json_decode($body, true);
		$errors = $res['errors'] ?? [];
		foreach ($errors as $error) {
			preg_match_all('/\[(.+)\]/', $error, $matches);
			$matches = $matches[1] ?? [];
			foreach ($matches as $match) {
				foreach (explode(' ', $match) as $barcode) {
					unset($part[$barcode]);
				}
			}
		}

		if (!empty($part) && count($part) < $count) {
			try {
				$this->api->apiPricesUpdate(array_values($part));
			} catch (Exception $ignore) {
			}
		}

		$this->addError($body);
	}

	/**
	 * @throws WrongArgumentException
	 */
	public function updateStock()
	{
		$update = $delete = [];
		foreach ($this->feedResult as $key => $line) {
			if ($key > 0 && isset($line[0]) && isset($line[1])) {
				$link = $this->getAssortmentLinkByAssortment($line[0]);
				$barcode = $link ? $link->getMpBarcode() : null;
				if ($barcode) {
					$rest = (int)$line[1];
					if ($rest > 0) {
						$update[] = [
							'sku' => $barcode,
							'amount' => $rest
						];
					} else {
						$delete[] = $barcode;
					}
				}
			}
		}

		if (!empty($delete)) {
			foreach (array_chunk($delete, self::STOCK_UPDATE_LIMIT) as $del) {
				try {
					$this->api->apiStocksDelete($del);
				} catch (UserException $e) {
					$this->addError($e->getMessage());
				}
			}
		}

		if (!empty($update)) {
			foreach (array_chunk($update, self::STOCK_UPDATE_LIMIT) as $upd) {
				try {
					$this->api->apiStocksUpdate($upd);
				} catch (UserException $e) {
					$this->addError($e->getMessage());
				}
			}
		}
	}
}
