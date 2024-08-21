<?php

namespace Stripmag\Marketplace;

use CurlHttpClient;
use HttpMethod;
use HttpRequest;
use HttpResponse;
use HttpStatus;
use HttpUrl;
use NetworkException;
use OldProject;
use Timestamp;
use WrongArgumentException;

abstract class BaseApi
{
	const LOG_DIR = '....._log/';
	private $errorsAuth = [HttpStatus::CODE_401, HttpStatus::CODE_403];
	private $errorsServer = [HttpStatus::CODE_500, HttpStatus::CODE_502, HttpStatus::CODE_503, HttpStatus::CODE_504];
	private $logName;
	/** @var HttpRequest */
	private $request;
	/** @var HttpResponse */
	private $response;

	protected $userErrors = [];

	abstract function isValid(): bool;

	/* @throws UserException */
	abstract function getPackagingOrders(): array;

	/**
	 * @return array | false
	 * @throws UserException
	 */
	abstract function getDeliveryOrdersMap();

	/* @throws UserException */
	abstract function changeOrderStatus(array $order);

	/* @throws UserException */
	abstract function getPackageLabel($orderId);

	abstract protected function createApiError(HttpResponse $response, HttpRequest $request): ApiException;

	public function enableLog($name)
	{
		$this->logName = $name;
	}

	protected function getDataFromJson($text)
	{
		return json_decode($text, true) ?: $text;
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	protected function processRequest(string $address, $data, HttpMethod $method = null)
	{
		try {
			$method = $method ?? HttpMethod::post();
			$this->request = $this->getApiRequest($address)->setMethod($method);
			$data = $this->modifyRequest($data, $this->request);

			if ($method->getId() === HttpMethod::GET) {
				if ($data) {
					$this->request->setGet($data);
				}
			} elseif ($data !== null) {
				$stringData = json_encode($data, JSON_UNESCAPED_UNICODE);
				$this->request->setBody($stringData);
			}

			$client = CurlHttpClient::create();
			if ($this->request->getBody()) {
				$client->setOption(CURLOPT_POSTFIELDS, $this->request->getBody());
			}

			$this->sendRequest($client);
			if (
				$this->response->getStatus()->getId() < HttpStatus::CODE_200
				|| $this->response->getStatus()->getId() > HttpStatus::CODE_206
			) {
				$this->processApiError();
			}

			$data = $this->getDataFromJson($this->response->getBody());
			return $this->processResponse($data);

		} catch (ApiException $e) {
			throw $e;
		} catch (NetworkException $e) {
			throw new UserException('Network error: ' . $e->getMessage());
		}
	}

	/**
	 * @throws WrongArgumentException
	 */
	private function getApiRequest($url): HttpRequest
	{
		$url = strpos($url, '://') ? $url : static::API_URL . $url;
		return HttpRequest::create()
			->setUrl(HttpUrl::parse($url))
			->setHeaderVar('Content-type', 'application/json; charset=utf-8')
			->setHeaderVar('Accept', 'application/json;charset=utf-8');
	}

	protected function modifyRequest($data, HttpRequest $request)
	{
		return $data;
	}

	protected function processResponse($data)
	{
		return $data;
	}

	/** @throws UserException */
	private function checkUserErrors($message)
	{
		array_map(function ($str) use ($message) {
			if (strpos($message, $str) !== false) {
				throw new UserException($message);
			}
		}, $this->userErrors);
	}

	/**
	 * @throws UserException
	 * @throws ApiException
	 */
	protected function processApiError()
	{
		$error = $this->createApiError($this->response, $this->request);
		$message = $error->getApiMessage();
		$code = $this->response->getStatus()->getId();

		if (in_array($code, $this->errorsAuth)) {
			throw new UserException("($code) Ошибка авторизации, проверьте учетные данные. $message");
		}
		if (in_array($code, $this->errorsServer)) {
			throw new UserException("($code) Ошибка на сервере маркетплейса. $message");
		}

		$this->checkUserErrors($error->getMessage());

		throw $error;
	}

	/**
	 * @throws NetworkException
	 */
	private function sendRequest(CurlHttpClient $client)
	{
		$this->response = $client->setFollowLocation(true)->send($this->request);
		if ($this->logName) {
			$this->addLog();
		}
	}

	private function addLog()
	{
		$json = [
			'time' => Timestamp::makeNow()->toString(),
			'request' => [
				'url' => $this->request->getUrl()->toString(),
				'method' => $this->request->getMethod()->toString(),
				'body' => $this->getDataFromJson($this->request->getBody())
			],
			'response' => [
				'status' => $this->response->getStatus()->getId(),
				'body' => $this->getDataFromJson($this->response->getBody()),
			]
		];

		$name = self::getLogFile($this->logName);
		$file = fopen($name, 'a');
		$stat = fstat($file);
		$size = $stat['size'];

		if (!$size) {
			fwrite($file, '[' . PHP_EOL);
		} else {
			ftruncate($file, $size - 1);
			fwrite($file, ',');
		}

		fwrite($file, json_encode($json) . PHP_EOL);
		fwrite($file, ']');
		fclose($file);
	}

	public static function getLogPath(): string
	{
		$logPath = OldProject::create(OldProject::ADMIN)->getRootDir() . self::LOG_DIR;
		if (!is_dir($logPath)) {
			mkdir($logPath);
		}
		return $logPath;
	}

	public static function getLogFile($logName): string
	{
		return self::getLogPath() . $logName . '.json';
	}
}
