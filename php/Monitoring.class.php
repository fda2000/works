<?php

namespace Stripmag\Marketplace;

use AssemblyPull;
use AssemblyPullStatus;
use AssemblyPullType;
use Criteria;
use Expression;
use IRunnable;
use MarketplaceDriverType;
use MarketplaceUserApiSettings;
use MissingElementException;
use NewMarketPlace;
use ObjectNotFoundException;
use Projection;
use Stripmag\Telegram\Bot;
use Timestamp;
use WrongArgumentException;
use WrongStateException;

class Monitoring implements IRunnable
{
	const SEND_FBS_RANGE = '+50 minutes';
	const SEND_FBS_RATE = 25;

	/** @var MarketplaceDriverType */
	private $driverType;


	public function __construct(MarketplaceDriverType $driverType)
	{
		$this->driverType = $driverType;
	}

	public static function create(MarketplaceDriverType $driverType): Monitoring
	{
		return new static($driverType);
	}

	/**
	 * @throws MissingElementException
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws WrongStateException
	 */
	public function run()
	{
		$startScript = Timestamp::makeNow();
		foreach ($this->driverType->getDriverExistIds() as $newMarketPlaceId) {
			$fakeSettings = MarketplaceUserApiSettings::create()->setMarketplaceId($newMarketPlaceId);
			$driver = $this->driverType->getDriver($fakeSettings);
			if (!($driver instanceof IHasSupply)) {
				continue;
			}

			$range = $driver->getSendFbsRange();
			if (!$range) {
				continue;
			}

			$end = $range->getEnd();
			$range->setStart($end);
			$range->setEnd($end->spawn(self::SEND_FBS_RANGE));
			if ($range->contains($startScript)) {
				$this->checkPulls($fakeSettings->getMarketplace());
			}
		}
	}

	/**
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 * @throws MissingElementException
	 * @throws WrongStateException
	 */
	private function checkPulls(NewMarketPlace $marketplace)
	{
		$types = [];
		foreach (AssemblyPullType::getList() as $type) {
			/** @var AssemblyPullType $type */
			if ($type->getNewMarketPlace()->getId() == $marketplace->getId()) {
				$types[] = $type->getId();
			}
		}

		$list = AssemblyPull::dao()->getAssemblyPulls(null, [AssemblyPullStatus::NEW, AssemblyPullStatus::ASSEMBLY])
			->addProjection(Projection::property('pullIid'))
			->addProjection(Projection::property('status', 'statusId'))
			->addProjection(Projection::property('user.company', 'companyId'))
			->add(Expression::in('type', $types))
			->setLimit(null)
			->dropOrder()
			->getCustomList();

		$companies = array_column($list, 'companyId');
		$enabled = $this->getEnabledCompanyIds($marketplace, $companies);

		$all = $new = 0;
		foreach ($list as $item) {
			if (!isset($enabled[$item['companyId']])) {
				continue;
			}
			if ($item['statusId'] == AssemblyPullStatus::NEW) {
				$new++;
			}
			$all++;
		}

		$this->sendTelegram($marketplace, $new, $all);
	}

	/**
	 * @throws MissingElementException
	 * @throws ObjectNotFoundException
	 * @throws WrongArgumentException
	 */
	public function getEnabledCompanyIds(NewMarketPlace $marketplace, array $companyIds): array
	{
		if (empty($companyIds)) {
			return [];
		}

		/** @var MarketplaceUserApiSettings[] $settings */
		$settings = Criteria::create(MarketplaceUserApiSettings::dao())
			->add(Expression::eqId('marketplace', $marketplace))
			->add(Expression::in('company', $companyIds))
			->getList();

		$enabled = [];
		foreach ($settings as $set) {
			if ($set->getSettingsDTO()->getValue('enableSendFbs')) {
				$enabled[$set->getCompanyId()] = true;
			}
		}

		return $enabled;
	}

	private function sendTelegram(NewMarketPlace $marketplace, int $new, int $all)
	{
		$name = $marketplace->getName();
		$sent = $all - $new;
		$rate = $all > 0 ? round($new / $all * 100) : 0;

		if ($rate > self::SEND_FBS_RATE) {
			$message = <<< EOT
<b>Ошибка в автоматической отправке FBS-отгрузок $name - не отправлено $rate% отгрузок!</b>
Всего отгрузок: $all
Отправлено: $sent
Не отправлено: $new
EOT;
		} else {
			$message = <<< EOT
FBS-отгрузки $name были успешно отправлены.
Всего отгрузок: $all
Отправлено: $sent
Не отправлено: $new ($rate%)
EOT;
		}

		$bot = new Bot();
		$bot->send(Bot::P5S_LETTERS_OF_HAPPINESS, $message, 'html');
	}
}
