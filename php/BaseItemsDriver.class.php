<?php

namespace Stripmag\Marketplace;

use Closure;
use ObjectNotFoundException;
use Stripmag\Export\SmartFeed\Builder;
use Stripmag\Export\SmartFeed\DTO\Fields;
use Stripmag\Export\SmartFeed\DTO\Settings;
use Timestamp;
use WrongArgumentException;
use WrongStateException;

abstract class BaseItemsDriver extends BaseDriver
{
	const SHORT_UPDATE_HOURS_LIMIT = 2;
	const FULL_UPDATE_ON_HOUR = 3;

	protected $feedResult = [];
	protected $enabledPartialUpdate = false;
	private $lastLimit;

	abstract function updateStock();

	abstract function updatePrice();

	/**
	 * @throws WrongArgumentException
	 */
	protected function init()
	{
		if ($this->isPartialUpdate()) {
			$this->lastLimit = Timestamp::makeNow()->spawn('-' . static::SHORT_UPDATE_HOURS_LIMIT . 'hours')->toStamp();
			$this->setFeedLimits();
		}
	}

	protected function isPartialUpdate(): bool
	{
		if (!$this->enabledPartialUpdate) {
			return false;
		}
		if ($this->startScript->getHour() == static::FULL_UPDATE_ON_HOUR) {
			return false;
		}

		$limit = $this->startScript->spawn('-1 hours -10 minutes')->toStamp();
		$lastUpdate = $this->feed->getLastModified()->toStamp();

		return $lastUpdate < $limit;
	}

	/**
	 * @throws WrongArgumentException
	 */
	private function setFeedLimits()
	{
		$fields = $this->feed->getFieldsDTO()->getFieldsEnablings();
		$fields['modified'] = true;
		$this->feed->setFieldsDTO(new Fields($fields));

		$settings = $this->feed->getSettingsDTO()->getSettingsValues();
		$settings['lastHoursModified'] = static::SHORT_UPDATE_HOURS_LIMIT;
		$this->feed->setSettingsDTO(new Settings($settings));
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 */
	public function run()
	{
		try {
			if ($this->feed && $this->isValid()) {
				$this->init();
				$this->feedResult = $this->getFeedResult();
				$this->updateStock();
				$this->updatePrice();
			}
		} catch (UserException $e) {
			$this->addError($e->getMessage());
		}
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongStateException
	 * @throws WrongArgumentException
	 */
	private function getFeedResult(): array
	{
		$feedBuilder = new Builder($this->feed);
		$feedBuilder->buildDTOList();
		$dtoList = $feedBuilder->getProductsDTOs();
		$builder = $this->feed->getType()->getFileBuilder($this->feed, $dtoList);
		return $builder->getArray();
	}

	protected function getFeedResultForUpdate()
	{
		$items = $this->feedResult;
		if ($this->isPartialUpdate()) {
			$items = array_filter($items, function ($item) {
				return $this->checkModified($item['modified'] ?? null);
			});
		}
		return $items;
	}

	private function checkModified($modified): bool
	{
		if ($this->lastLimit) {
			$modified = $modified ? Timestamp::create($modified)->toStamp() : 0;
			if ($modified < $this->lastLimit) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Вызывает $function с разбивкой по количеству элементов в запросе и числу элементов в интервале времени
	 * @param array $limits ['maxItems' => максимальное число элементов в запросе, 'timeItems' => максимальное число элементов в интервал времени, 'nextTime' => модификатор интервала времени]
	 * TODO Переделать на rabbit? Тогда уж и Озон сюда же. А может и вообще глобально все сюда...
	 */
	protected function callByLimits(array $update, Closure $function, array $limits)
	{
		$limitCallTime = $sent = 0;
		foreach (array_chunk($update, $limits['maxItems']) as $upd) {
			try {
				$countItems = count($upd);
				if ($sent + $countItems > $limits['timeItems']) {
					$sleep = $limitCallTime - Timestamp::makeNow()->toStamp();
					if ($sleep > 0) {
						sleep($sleep);
					}
					$limitCallTime = 0;
				}

				if (!$limitCallTime) {
					$limitCallTime = Timestamp::makeNow()->spawn($limits['nextTime'])->toStamp();
					$sent = 0;
				}

				$function($upd);
				$sent += $countItems;
			} catch (UserException $e) {
				$this->addError($e->getMessage());
			}
		}
	}
}
