<?php

namespace Stripmag\Marketplace;

use AssemblyPull;
use TimestampRange;

interface IHasSupply
{
	public function isFbsEnabled(): bool;

	public function checkSupplies();

	public function deliverySupply();

	public function loadSupplyBarcode(): bool;

	public function updateLog();

	public function setAssemblyPull(AssemblyPull $assemblyPull);

	/**
	 * Возвращает время запуска автоматической отправки отгрузки FBS
	 * @return TimestampRange | null
	 */
	public function getSendFbsRange();
}
