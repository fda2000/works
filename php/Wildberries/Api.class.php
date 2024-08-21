<?php

namespace Stripmag\Marketplace\Wildberries;

use DOMDocument;
use HttpMethod;
use HttpRequest;
use HttpResponse;
use Stripmag\Marketplace\ApiException;
use Stripmag\Marketplace\BaseApi;
use Stripmag\Marketplace\UserException;
use WrongArgumentException;

class Api extends BaseApi
{
	const API_URL = 'https://suppliers-api.wildberries.ru';
	const MIN_LABEL_SIZE = 1500;
	const SUPPLIES_GET_LIMIT = 1000;

	protected $userErrors = [
		'500 Internal Server Error',
		'invalid token',
		'Доступ запрещён',
		'unauthorized',
		'Не удалось авторизоваться',
		'Cклады не найдены',
		'SupplyHasZeroOrders',
		'SupplyNotClosed'
	];

	private $apiKey;
	private $warehouseId;

	private $supplyCache = [];

	public function __construct($apiKey, $warehouseId)
	{
		$this->apiKey = $apiKey;
		$this->warehouseId = $warehouseId;
	}

	public function isValid(): bool
	{
		return !!$this->apiKey && $this->warehouseId > 0;
	}

	/**
	 * @throws ApiException
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	public function getPackagingOrders(): array
	{
//		return json_decode('[{"address":null,"deliveryType":"fbs","user":null,"orderUid":"33421512_5e70239c6624486ba2c97cc64fde7f58","article":"343201","rid":"428fe17682c34f519871cc7e5940a9bc","createdAt":"2023-09-20T08:06:03Z","offices":["\u041c\u043e\u0441\u043a\u0432\u0430"],"skus":["2037616671179"],"prioritySc":["\u041c\u044b\u0442\u0438\u0449\u0438"],"id":645405073,"warehouseId":155790,"nmId":14088471,"chrtId":42128203,"price":39100,"convertedPrice":39100,"currencyCode":643,"convertedCurrencyCode":643,"isLargeCargo":false}]', true);
		$data = $this->processRequest('/api/v3/orders/new', [], HttpMethod::get());
		return $data['orders'];
	}

	public function getDeliveryOrdersMap(): bool
	{
		return false;
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function changeOrderStatus(array $order)
	{
		$this->addToSupply($order['id'], $order['supplyId']);
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function getPackageLabel($orderId)
	{
		$orderId = (int)$orderId;
		return $this->apiPackageLabels([
			'orders' => [$orderId]
		]);
	}

	/**
	 * @throws WrongArgumentException
	 * @throws ApiException
	 * @throws UserException
	 */
	private function apiPackageLabels(array $data)
	{
		//return json_decode('{"stickers":[{"partA":"953019","partB":"5608","barcode":"*jgLPpiA","file":"iVBORw0KGgoAAAANSUhEUgAAAkQAAAGQCAIAAADAxeipAABDpElEQVR4nOzdeVxTV...","orderId":645405073}]}', true);
		$res = $this->processRequest('/api/v3/orders/stickers?type=svg&width=58&height=40', $data);
		if ($res && isset($res['stickers']) && isset($res['stickers'][0]['file'])) {
			$content = base64_decode($res['stickers'][0]['file']);
			if ($content && strlen($content) > self::MIN_LABEL_SIZE) {
				return $this->customizeSize($content);
			}
		}
		return false;
	}

	/** Подгоняем SVG под нужный размер */
	private function customizeSize($content)
	{
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->loadXML($content);
		$svg = $dom->documentElement;
		$svg->setAttribute('width', '380');
		$svg->setAttribute('height', '200');
		return $dom->saveXML();
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function apiPricesUpdate(array $data)
	{
		//throw new ApiException('message', 400, '{"errors":["данных номенклатур не было в выгруженном с портала шаблоне: [286877], добавление строк в шаблон запрещено\nна следующие номенклатуры указана слишком высокая цена. Рост более 20 процентов: [90956412 91117798 91122927 94117974 97677988 98110826 98164452 98168036 100214977 100214978 100217550 100359497 102664672 102678417 102683183 102879298 107223041 123113883 123121287 124828401 124836248 124837084]"]}');
		$this->processRequest('https://discounts-prices-api.wb.ru/api/v2/upload/task', ['data' => $data]);
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	public function apiStocksUpdate(array $data)
	{
		try {
			$this->processRequest('/api/v3/stocks/' . $this->warehouseId, ['stocks' => $data], HttpMethod::createByName('PUT'));
		} catch (ApiException $e) {
			throw new UserException('stocks update error: ' . $e->getResponse()->getBody());
		}
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 */
	public function apiStocksDelete(array $data)
	{
		try {
			$this->processRequest('/api/v3/stocks/' . $this->warehouseId, ['skus' => $data], HttpMethod::createByName('DELETE'));
		} catch (ApiException $e) {
			if ($e->getCode() != 404) {
				throw new UserException('stocks delete error: ' . $e->getResponse()->getBody());
			}
		}
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function findSupplyByName(string $name)
	{
		foreach ($this->supplyCache as $supply) {
			if ($supply['name'] == $name) {
				return $supply;
			}
		}

		$ret = null;
		$page = 0;
		do {
			$data = $this->getSupplies($page);
			foreach ($data['supplies'] ?? [] as $supply) {
				$this->supplyCache[$supply['id']] = $supply;
				if ($supply['name'] == $name) {
					$ret = $supply;
				}
			}
			if ($ret) {
				return $ret;
			}

			$page = $data['next'] ?? 0;
		} while ($page);

		return null;
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	public function getSupplies($page)
	{
		return $this->processRequest('/api/v3/supplies', ['next' => $page, 'limit' => self::SUPPLIES_GET_LIMIT], HttpMethod::get());
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	public function getSupplyById(string $id)
	{
		$supply = $this->processRequest("/api/v3/supplies/$id", null, HttpMethod::get());
		$this->supplyCache[$supply['id']] = $supply;
		return $supply;
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function createSupply(string $name)
	{
		$supply = $this->processRequest('/api/v3/supplies', ['name' => $name], HttpMethod::post());
		$supply['name'] = $name;
		$supply['done'] = false;
		$this->supplyCache[$supply['id']] = $supply;
		return $supply;
	}

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	public function addToSupply($orderId, $supplyId)
	{
		$this->processRequest("/api/v3/supplies/$supplyId/orders/$orderId", null, HttpMethod::createByName('PATCH'));
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	public function getSupplyOrders($supplyId)
	{
		$orders = $this->processRequest("/api/v3/supplies/$supplyId/orders", null, HttpMethod::get());
		return $orders['orders'];
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	public function deliverySupply($supplyId)
	{
		$this->processRequest("/api/v3/supplies/$supplyId/deliver", null, HttpMethod::createByName('PATCH'));
	}

	/**
	 * @throws WrongArgumentException
	 * @throws ApiException
	 * @throws UserException
	 */
	public function getSupplyBarcode($supplyId)
	{
		$res = $this->processRequest("/api/v3/supplies/$supplyId/barcode", ['type' => 'svg'], HttpMethod::get());
		if ($res['file'] ?? null) {
			$content = base64_decode($res['file']);
			if ($content && strlen($content) > self::MIN_LABEL_SIZE) {
				return $this->customizeSize($content);
			}
		}
		return false;
	}

	protected function modifyRequest($data, HttpRequest $request)
	{
		$request->setHeaderVar('Authorization', $this->apiKey);
		return $data;
	}

	protected function createApiError(HttpResponse $response, HttpRequest $request): ApiException
	{
		$body = $response->getBody();
		$arr = $this->getDataFromJson($body);
		$code = $response->getStatus()->getId();
		$apiMessage = $arr['errorText'] ?? '';
		$message = $apiMessage ?: $body;

		$message = 'API Wildberries error in ' . $request->getUrl()->getPath() .
			' (' . $code . '): ' . $message .
			', apiKey=' . $this->apiKey;

		return ApiException::create($message, (int)$code)->setApiMessage($apiMessage)->setResponse($response);
	}
}
