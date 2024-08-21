<?php

namespace Stripmag\Marketplace;

use Exception;
use IRunnable;
use MarketplaceUserApiSettings;
use MarketplaceUserAssortmentLink;
use SmartFeed;
use Timestamp;

abstract class BaseDriver implements IRunnable
{
	const TEXT_LOG_ERROR = ' #Error';
	const TEXT_LOG_NOTE = ' #Note';
	/** @var MarketplaceUserApiSettings */
	protected $settings;
	/** @var SmartFeed */
	protected $feed;
	/** @var BaseApi */
	protected $api;

	/** @var string[] */
	private $errors = [];
	/** @var MarketplaceUserAssortmentLink[] */
	private $linksArticle = [];
	/** @var MarketplaceUserAssortmentLink[] */
	private $linksBarcode = [];
	/** @var MarketplaceUserAssortmentLink[] */
	private $linksAssortment = [];
	/** @var Timestamp */
	protected $startScript;

	public function __construct(MarketplaceUserApiSettings $settings)
	{
		$this->settings = $settings;
		try {
			$this->feed = $settings->getFeed();
		} catch (Exception $e) {
		}
	}

	public function setStart(Timestamp $startScript)
	{
		$this->startScript = $startScript;
	}

	protected function isEmptyLinks(): bool
	{
		return empty($this->linksArticle);
	}

	/** @param MarketplaceUserAssortmentLink[] $assortmentLinks */
	public function setAssortmentLinks(array $assortmentLinks)
	{
		array_walk($assortmentLinks, function ($element) {
			/** @var MarketplaceUserAssortmentLink $element */
			$this->linksArticle[$element->getProductArticle()] = $element;
			$this->linksBarcode[$element->getMpBarcode()] = $element;
			$this->linksAssortment[$element->getAssortmentId()] = $element;
		});
	}

	protected function getAssortmentLinkByAssortment($assortmentId)
	{
		return $this->linksAssortment[$assortmentId] ?? null;
	}

	protected function getAssortmentLinkByArticle($article)
	{
		return $this->linksArticle[$article] ?? null;
	}

	protected function getAssortmentLinkByBarcode($barcode)
	{
		return $this->linksBarcode[$barcode] ?? null;
	}

	protected function isValid(): bool
	{
		return $this->api->isValid();
	}

	protected function addInfo(string $text)
	{
		$this->errors[] = $text;
	}

	protected function addError(string $text)
	{
		$this->errors[] = $text . self::TEXT_LOG_ERROR;
	}

	protected function addNote(string $text)
	{
		$this->errors[] = $text . self::TEXT_LOG_NOTE;
	}

	public function getErrors(): array
	{
		return $this->errors;
	}

	public function getLogName(): string
	{
		return $this->settings->getCompanyId() . '-' . $this->getNamespaceMarketplace();
	}

	private function getNamespaceMarketplace()
	{
		$names = explode('\\', get_class($this));
		array_pop($names);
		return array_pop($names);
	}
}
