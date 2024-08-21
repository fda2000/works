<?php

namespace Stripmag\Marketplace;

use Stripmag\Exception\ApiRequestException;

class ApiException extends ApiRequestException
{
	protected $apiMessage;

	public static function create($message, $code): ApiException
	{
		return new self($message, $code);
	}

	public function setApiMessage($apiMessage): ApiException
	{
		$this->apiMessage = $apiMessage;
		return $this;
	}

	public function getApiMessage(): string
	{
		return $this->apiMessage;
	}
}
