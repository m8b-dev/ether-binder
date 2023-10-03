<?php

namespace M8B\EtherBinder\RPC;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\HashSerializable;
use M8B\EtherBinder\Common\Log;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * RPCFilter is class that represents installed filter on RPC. It's meant for filtering logs.
 *
 */
class RPCFilter
{
	protected int $lastSeenBlockNumber = 0;
	protected OOGmp $filterId;
	protected bool $firstPass = true;

	/**
	 * Instantiates new RPC filter. Uses same parameters as AbstractRPC::ethNewFilter and RPC instance.
	 *
	 * @see AbstractRPC::ethNewFilter()
	 *
	 * @param AbstractRPC $rpc
	 * @param Address|array $address
	 * @param int|BlockParam|null $fromBlock
	 * @param int|BlockParam|null $toBlock
	 * @param string|bool|HashSerializable|array $topic0
	 * @param string|bool|HashSerializable|array|null $topic1
	 * @param string|bool|HashSerializable|array|null $topic2
	 * @param string|bool|HashSerializable|array|null $topic3
	 * @throws EthBinderArgumentException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function __construct(
		protected AbstractRPC $rpc,
		protected Address|array $address,
		protected null|int|BlockParam $fromBlock,
		protected null|int|BlockParam $toBlock,
		protected string|bool|HashSerializable|array $topic0,
		protected null|string|bool|HashSerializable|array $topic1 = null,
		protected null|string|bool|HashSerializable|array $topic2 = null,
		protected null|string|bool|HashSerializable|array $topic3 = null)
	{
		$this->loadFilterID(false);
	}

	/**
	 * If toBlock was defined to concrete block number, will return false if the last seen block number is greater than
	 * provided toBlock in constructor.
	 *
	 * @return bool
	 */
	public function isDone(): bool
	{
		if(!is_int($this->toBlock))
			return false;
		return $this->lastSeenBlockNumber >= $this->toBlock;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	protected function loadFilterID(bool $useInternalCounter = true): void
	{
		if($this->isDone())
			return;

		if($useInternalCounter)
			$this->filterId = $this->rpc->ethNewFilter(
				$this->address,
				$this->fromBlock,
				$this->toBlock,
				$this->topic0,
				$this->topic1,
				$this->topic2,
				$this->topic3);
		else
				$this->filterId = $this->rpc->ethNewFilter(
					$this->address,
					$this->lastSeenBlockNumber + 1,
					$this->toBlock,
					$this->topic0,
					$this->topic1,
					$this->topic2,
					$this->topic3);

		$this->firstPass = true;
	}

	/**
	 * Fetches new logs from the filter
	 *
	 * @return Log[]
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 * @throws EthBinderArgumentException
	 * @throws EthBinderRuntimeException
	 */
	public function fetchNew(): array
	{
		if($this->isDone()) return [];

		try {
			$o = $this->doFetchNew();
		} catch(RPCGeneralException $e) {
			if($e->getMessage() == "filter not found") {
				$this->loadFilterID($this->filterId->gt(0));
				$o = $this->doFetchNew();
			} else {
				throw $e;
			}
		}
		return $o;
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexLengthException
	 * @throws RPCNotFoundException
	 * @throws InvalidHexException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	protected function doFetchNew(): array
	{
		if($this->firstPass) {
			$o = $this->rpc->ethGetFilterLogs($this->filterId);
			$this->firstPass = false;
		} else {
			$unparsedLogs = $this->rpc->ethGetFilterChanges($this->filterId);
			$o = [];
			foreach($unparsedLogs as $unparsedLog) {
				$o[] = Log::fromRPCArr($unparsedLog);
			}
		}

		if(!empty($o)) {
			foreach($o AS $log) {
				if($this->lastSeenBlockNumber < $log->blockNumber)
					$this->lastSeenBlockNumber = $log->blockNumber;
			}
		} else {
			$blockNum = $this->rpc->ethBlockNumber() - 1 /* -1 since the value can change during rpc call */;
			if($this->lastSeenBlockNumber < $blockNum)
				$this->lastSeenBlockNumber = $blockNum;
		}
		return $o;
	}
}
