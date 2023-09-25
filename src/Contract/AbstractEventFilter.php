<?php

namespace M8B\EtherBinder\Contract;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\HashSerializable;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\RPC\BlockParam;
use M8B\EtherBinder\RPC\RPCFilter;

abstract class AbstractEventFilter
{
	protected AbstractRPC         $rpc;
	protected Address             $target;
	protected array               $filterParams = [];
	protected ?RPCFilter          $rpcFilter    = null;
	protected array               $buffer       = [];
	protected null|int|BlockParam $fromBlock    = null;
	protected null|int|BlockParam $toBlock      = null;

	public abstract static function eventClassName(): string;
	protected abstract static function validatorTypes(): array;

	public function isDone(): bool
	{
		if($this->rpcFilter === null)
			return false;
		return $this->rpcFilter->isDone();
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	public function setFromBlock(null|int|BlockParam $fromBlock): static
	{
		if($this->rpcFilter !== null)
			throw new EthBinderArgumentException("set from and to block before calling fetchNext()");
		$this->fromBlock = $fromBlock;
		return $this;
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	public function setToBlock(null|int|BlockParam $toBlock): static
	{
		if($this->rpcFilter !== null)
			throw new EthBinderArgumentException("set from and to block before calling or installFilter() fetchNext()");
		$this->toBlock = $toBlock;
		return $this;
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	public function installFilter(): void
	{
		$this->validateParams();
		$this->rpcFilter = new RPCFilter(
			$this->rpc,
			$this->target,
			$this->fromBlock,
			$this->toBlock,
			static::eventClassName()::abiEventID(),
			$this->filterParams[0] ?? null,
			$this->filterParams[1] ?? null,
			$this->filterParams[2] ?? null,
		);
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws EthBinderRuntimeException
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexLengthException
	 * @throws RPCNotFoundException
	 * @throws InvalidHexException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	protected function parseFetchNext(): ?AbstractEvent
	{
		if(!empty($this->buffer))
			return array_shift($this->buffer);
		if($this->rpcFilter === null) {
			$this->installFilter();
		}
		$logs = $this->rpcFilter->fetchNew();
		if(empty($logs))
			return null;
		$parsedLogs = [];
		foreach($logs AS $log) {
			$parsedLog = static::eventClassName()::parseEventFromLog($log);
			if($parsedLog === null)
				continue;
			$parsedLogs[] = $parsedLog;
		}
		if(empty($parsedLogs))
			return null;
		$this->buffer = $parsedLogs;
		return array_shift($this->buffer);
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws EthBinderArgumentException
	 */
	protected function validateParams(): void
	{
		foreach(static::validatorTypes() AS $k => $wantType) {
			if(!key_exists($k, $this->filterParams))
				throw new EthBinderLogicException("Filter is not set, should be null. Bad binding?");
			if($this->filterParams[$k] === null)
				continue;
			if(is_array($this->filterParams[$k])) {
				foreach($this->filterParams[$k] as $kk => $filter)
					$this->assertType($filter, $wantType, $k."[$kk]");
			} else {
				$this->assertType($this->filterParams[$k], $wantType, $k);
			}
		}
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	protected function assertType($instance, string $wantType, string $index): void
	{
		if(!match ($wantType) {
			"bool"   => is_bool($instance),
			"string" => is_string($instance),
			default  => $instance instanceof $wantType
		})
			throw new EthBinderArgumentException("Bound filter was constructed with invalid type at index $index: "
			.". Expected type $wantType, but got ".(gettype($instance) === "object" ? $instance::class : gettype($instance)));
	}
}
