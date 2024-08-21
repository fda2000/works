<?php

namespace Stripmag\Marketplace;

use Criteria;
use DateTimeZone;
use DirectoryIterator;
use ErrorUtils;
use Exception;
use Expression;
use FetchStrategy;
use IRunnable;
use LogUtils;
use MarketplaceDriverType;
use MarketplaceUserApiSettings;
use MarketplaceUserAssortmentLink;
use MissingElementException;
use ObjectNotFoundException;
use Timestamp;

class Process implements IRunnable
{
	const LOG_NAME = 'MarketplaceApi';
	const RUN_LOG_NAME = 'RunMarketplaceApi';

	/** @var MarketplaceUserApiSettings[][] */
	private $settingsUsers = [];
	private $driverType;
	/** @var Timestamp */
	private $startScript;


	public function __construct(MarketplaceDriverType $driverType)
	{
		$this->driverType = $driverType;
	}

	public static function create(MarketplaceDriverType $driverType): Process
	{
		return new static($driverType);
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws MissingElementException
	 * @throws Exception
	 */
	public function run()
	{
		$this->startScript = Timestamp::makeNow();
		$this->addRunLog('Started');
		if ($this->driverType->getId() == MarketplaceDriverType::ITEMS && $this->startScript->getHour() == 2) {
			$this->deleteApiLogs();
		}

		$this->buildSettings();
		if (!$this->settingsUsers) {
			return;
		}

		foreach ($this->settingsUsers as $userId => $settingsMarkets) {
			foreach ($settingsMarkets as $settings) {
				$userLinks = $this->buildLinks($userId, $settings->getMarketplaceId());
				if (!empty($userLinks)) {
					$this->runDriver($settings, $userLinks);
				}
			}
		}

		if ($this->startScript->getHour() > 22 && $this->startScript->getMinute() > 44) {
			LogUtils::sendMailLog(self::LOG_NAME, '...');
		}

		$end = Timestamp::makeNow();
		$duration = $end->toStamp() - $this->startScript->toStamp();
		$duration = Timestamp::create($duration, new DateTimeZone('UTC'));
		$this->addRunLog('Started in ' . $this->startScript->toTime() . ' ended, duration = ' . $duration->toTime());
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws MissingElementException
	 */
	private function buildSettings()
	{
		MarketplaceUserApiSettings::proto()
			->getPropertyByName('company')
			->setFetchStrategy(FetchStrategy::join());

		$this->settingsUsers = [];
		foreach ($this->driverType->getApiSettingsList() as $settings) {
			$userId = $settings->getCompany()->getRegularUserId();
			$this->settingsUsers[$userId] = $this->settingsUsers[$userId] ?? [];
			$this->settingsUsers[$userId][$settings->getId()] = $settings;
		}

		MarketplaceUserApiSettings::proto()
			->getPropertyByName('company')
			->setFetchStrategy(FetchStrategy::lazy());
	}

	/**
	 * @throws ObjectNotFoundException
	 * @return MarketplaceUserAssortmentLink[]
	 */
	private function buildLinks($userId, $marketplaceId): array
	{
		MarketplaceUserAssortmentLink::dao()->uncacheLists();
		return Criteria::create(MarketplaceUserAssortmentLink::dao())
			->add(Expression::eq('marketplace', $marketplaceId))
			->add(Expression::eq('user', $userId))
			->getList();
	}

	/**
	 * @throws Exception
	 * TODO make async
	 */
	private function runDriver(MarketplaceUserApiSettings $settings, $userLinks)
	{
		$driver = $this->driverType->getDriver($settings);
		try {
			$driver->setStart($this->startScript);
			$driver->setAssortmentLinks($userLinks);
			$driver->run();
			$errors = $driver->getErrors();
		} catch (Exception $e) {
			$this->processException($e);
			$errors = $driver->getErrors();
			$errors[] = 'Unknown error';
		}

		$errors = array_map(function ($error) {
			return $this->driverType->getName() . ': ' . $error;
		}, $errors);

		$this->updateSettingsRun($settings, $errors);
	}

	/**
	 * @throws Exception
	 */
	private function processException(Exception $e)
	{
		if (defined('__LOCAL_DEBUG__') && __LOCAL_DEBUG__) {
			throw $e;
		}
		LogUtils::getLogger(self::LOG_NAME)->warning(ErrorUtils::makeExceptionErrorMessageText($e));
	}

	private function updateSettingsRun(MarketplaceUserApiSettings $settings, array $errors)
	{
		try {
			// Мы не можем быть уверены, что за время с запуска скрипта логи не обновились
			$id = $settings->getId();
			$dao = $settings->dao();
			$dao->uncacheById($id);
			$dao->dropObjectIdentityMapById($id);
			/** @var MarketplaceUserApiSettings $settings */
			$settings = $dao->getById($id);

			$dao->unite(
				(clone $settings)->setLastRun(Timestamp::makeNow())->updateErrors($errors),
				$settings
			);
		} catch (ObjectNotFoundException $e) {
			// Значит успели удалить
		}
	}

	private function deleteApiLogs()
	{
		$dir = new DirectoryIterator(BaseApi::getLogPath());
		/** @var DirectoryIterator $file */
		foreach ($dir as $file) {
			if ($file->isFile()) {
				unlink($file->getPathname());
			}
		}
	}

	private function addRunLog(string $string)
	{
		$string = $this->driverType->toString() . ': ' . $string;
		LogUtils::getLogger(self::RUN_LOG_NAME)->info($string);
	}
}
