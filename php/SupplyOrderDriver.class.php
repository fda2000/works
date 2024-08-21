<?php

namespace Stripmag\Marketplace;

use Administrator;
use AssemblyPull;
use AssemblyPullHelper;
use AssemblyPullLogHelper;
use AssemblyStatusHelper;
use DsMailBuilder;
use Exception;
use FileUtils;
use LogicException;
use MissingElementException;
use ObjectNotFoundException;
use ShopOrder;
use ShopUtils;
use Stripmag\AssemblyPull\Loader;
use Stripmag\AssemblyPull\PrimitiveBarcode;
use Stripmag\Omnidesk\Omnidesk;
use TelegramNotification;
use TelegramUsers;
use TimestampRange;
use WrongArgumentException;
use WrongStateException;

abstract class SupplyOrderDriver extends BaseOrderDriver implements IHasSupply
{
	/** @var AssemblyPull */
	private $currentPull;

	/**
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 */
	abstract public function getSupplyOrderIds($supplyId): array;

	/**
	 * Возвращает время запуска автоматической отправки отгрузки FBS
	 * @return TimestampRange | null
	 */
	public function getSendFbsRange()
	{
		return null;
	}

	/**
	 * @throws WrongArgumentException
	 * @throws UserException
	 * @throws ApiException
	 */
	abstract public function getSupplyBarcode($supplyId);

	public function run()
	{
		if ($this->isValid() && $this->isFbsEnabled()) {
			$this->processFbs();
		}
		parent::run();
	}

	/**
	 * @throws MissingElementException
	 */
	public function isFbsEnabled(): bool
	{
		$fbs = $this->settings->getSettingsDTO();
		$fbs = $fbs->enableFbs ?? null;
		return $this->settings->getSettingsDTO()->isSingleWarehouse() && $fbs;
	}

	public function updateLog()
	{
		$this->settings = $this->settings->dao()->unite(
			(clone $this->settings)->updateErrors($this->getErrors()),
			$this->settings
		);
	}

	public function setAssemblyPull(AssemblyPull $assemblyPull = null)
	{
		$this->currentPull = $assemblyPull;
	}

	/**
	 * @throws ObjectNotFoundException
	 * @return AssemblyPull | null
	 */
	protected function getCurrentPull($externalId = null)
	{
		if (!$this->currentPull) {
			$fakeOrder = ShopOrder::create()
				->setUser($this->settings->getCompany()->getRegularUser())
				->setTypeId(current($this->orderTypesIds));

			$helper = AssemblyPullHelper::create();
			if ($externalId) {
				$helper->setExternalId($externalId);
			}
			$this->setAssemblyPull($helper->getAssemblyPullNew($fakeOrder));
		}

		return $this->currentPull;
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws ApiException
	 * @throws UserException
	 */
	public function checkSupplies()
	{
		$internal = $ordersMap = $orders = [];
		$currentPull = $this->getCurrentPull();
		$externalId = $currentPull->getExternalId();
		if (!$externalId) {
			throw new UserException("У отгрузки {$currentPull->getId()} пустой externalId");
		}

		/** @var ShopOrder $order */
		foreach (ShopOrder::dao()->getOrdersByAssemblyPull($currentPull)->getList() as $order) {
			$internal[$order->getId()] = $order->getId();
			$ordersMap[$order->getExternalId()] = $order->getId();
		}

		foreach ($this->getSupplyOrderIds($externalId) as $extId) {
			$id = $ordersMap[$extId] ?? null;
			unset($internal[$id]);
			$orders[] = [
				'id' => $id,
				'externalId' => $extId
			];
		}

		foreach ($internal as $id) {
			$orders[] = [
				'id' => $id,
				'externalId' => null
			];
		}

		$ok = array_reduce($orders, function ($prev, $item) {
			return $prev && isset($item['id']) && isset($item['externalId']);
		}, true);

		if ($ok) {
			return true;
		}

		return $orders;
	}

	/**
	 * @throws ApiException
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 * @throws ObjectNotFoundException
	 */
	public function loadSupplyBarcode(): bool
	{
		$currentPull = $this->getCurrentPull();
		$externalId = $currentPull->getExternalId();
		$content = $this->getSupplyBarcode($externalId);
		if (!$content) {
			return false;
		}

		$tmpFile = FileUtils::makeTempFile();
		if (!file_put_contents($tmpFile, $content)) {
			throw new LogicException('Ошибка записи файла');
		}

		$primitive = PrimitiveBarcode::create('file')
			->required()
			->setValue($tmpFile)
			->setAllowedMimeTypes([])
			->disableCheckUploaded();

		$robot = Administrator::dao()->getRobot();
		Loader::create()->setAdmin($robot)->uploadBarcode($currentPull, $primitive);
		$this->addNote("Скачана этикетка для FBS отгрузки {$currentPull->getId()}");
		return true;
	}

	/**
	 * @throws ApiException
	 * @throws ObjectNotFoundException
	 * @throws UserException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 */
	protected function loadSupplyLabelAndChangeStatus()
	{
		if (!$this->loadSupplyBarcode()) {
			throw new UserException('Этикетка для поставки МП не получена');
		}

		$robot = Administrator::dao()->getRobot();
		AssemblyStatusHelper::create($this->getCurrentPull())->changeStatusProcess($robot);
	}

	protected function sendFbsResult($error)
	{
		try {
			$currentPull = $this->getCurrentPull();
		} catch (Exception $e) {
			$currentPull = null;
		}
		$currentPull = $currentPull ?: AssemblyPull::create();

		$company = $this->settings->getCompany();
		$user = $company->getRegularUser();
		$email = $user->getEmail();
		$mpName = $this->settings->getMarketplace()->getName();
		$replace = [
			'assemblyId' => $currentPull->getId(),
			'externalId' => $currentPull->getExternalId(),
			'companyName' => $company->getName(),
			'assemblyTypeName' => $currentPull->getType() ? $currentPull->getType()->getName() : null,
			'assemblyLink' => ShopUtils::buildFrontUrl($currentPull, true)
		];

		if ($error) {
			$error = $error === true ? 'Unknown error' : $error;
			$replace['error'] = $error;

			if ($currentPull->getId()) {
				$robot = Administrator::dao()->getRobot();
				$aPullLogHelper = new AssemblyPullLogHelper($currentPull, $robot);
				$aPullLogHelper->logAssemblyPullChangeStatusError($error);
			}

			$message = "Ошибка! FBS-отгрузка $mpName $replace[assemblyId] ($replace[externalId]) не была отправлена в автоматическом режиме.
			 Зайдите в свой личный кабинет и попробуйте оформить отгрузку в ручном режиме.
			 Ошибка: $error";
			Omnidesk::create()
				->setUser($email, $user->getAnyName())
				->setUserPhone($company->getPhone()->getMobile())
				->setSubject('Ошибка отправки FBS-отгрузки в автоматическом режиме')
				->setContent($message)
				->setGroup(Omnidesk::GROUP_P5S_RU_CLIENT_DEPARTMENT)
				->addLabelId(Omnidesk::LABEL_ERROR_FBS)
				->setInitiatorId(Omnidesk::STAFF_DANILA_PANK)
				->setCaseEmailId(Omnidesk::CLIENT_OPT)
				->send();

			$this->sendTelegram($replace, 'ERROR');
		} else {
			if ($email) {
				$message = "Ваша отгрузка $mpName $replace[assemblyId] ($replace[externalId]) была отправлена в автоматическом режиме.";
				DsMailBuilder::create()
					->setTo($email)
					->setFromMail('...')
					->setFromName('Поставщик счастья')
					->setSubject('Успешная отправка FBS-отгрузки в автоматическом режиме')
					->setText($message)
					->send();
			}

			$this->sendTelegram($replace, 'OK');
		}
	}

	private function sendTelegram(array $replace, string $name)
	{
		$company = $this->settings->getCompany();
		if (!$company->getHelper()->needNotice(TelegramNotification::ASSEMBLY_AUTO_SEND, 'telegram')) {
			return;
		}

		/** @var TelegramNotification $notify */
		$notify = TelegramNotification::dao()->getById(TelegramNotification::ASSEMBLY_AUTO_SEND);
		$text = $notify->getReplacedNamedTemplate($replace, $name);
		/** @var TelegramUsers[] $telegramUsers */
		$telegramUsers = $company->getRegularUser()->getTelegramUsers()->getList();
		foreach ($telegramUsers as $telegram) {
			$bot = $telegram->getBot();
			if ($bot->isUserBot()) {
				$bot->api()->send($telegram->getTelegramKey(), $text, 'html');
			}
		}
	}
}
