<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Block;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\HashSerializable;
use M8B\EtherBinder\Common\Log;
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Contract\AbstractEvent;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\Exceptions\UnexpectedUnsignedException;
use M8B\EtherBinder\RPC\BlockParam;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

abstract class Eth extends Debug
{
	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethProtocolVersion(): int
	{
		return $this->runRpc("eth_protocolVersion")[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSyncing(): false|array
	{
		$d = $this->runRpc("eth_syncing");
		if(isset($d[0]) && $d[0] === false) {
			return false;
		}

		$return = [];
		$keys = ["startingBlock", "currentBlock", "highestBlock", "pulledStates", "knownStates"];
		foreach($keys AS $key) {
			$return[$key] = new OOGmp($d[$key] ?? 0, 16);
		}
		return $return;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 * @throws EthBinderLogicException
	 */
	public function ethCoinbase(): Address
	{
		try {
			return Address::fromHex($this->runRpc("eth_coinbase")[0]);
		} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	private ?int $cachedChainId = null;

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethChainID(): int
	{
		if($this->cachedChainId === null)
			$this->cachedChainId = (int)hexdec($this->runRpc("eth_chainId")[0]);
		return $this->cachedChainId;
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethMining(): bool
	{
		return $this->runRpc("eth_mining")[0];
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethHashrate(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_hashrate")[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGasPrice(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_gasPrice")[0]);
	}

	/**
	 * @return Address[]
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethAccounts(): array
	{
		$accountsRaw = $this->runRpc("eth_accounts");
		$return = [];
		foreach($accountsRaw AS $accountRaw) {
			try {
				$return[] = Address::fromHex($accountRaw);
			} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
				throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
			}
		}
		return $return;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethBlockNumber(): int
	{
		return (new OOGmp($this->runRpc("eth_blockNumber")[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetBalance(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getBalance",
			[$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethGetStorageAt(Address $address, OOGmp $position, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getStorageAt",
			[$address->toHex(true), $position->toString(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionCount(Address $address, int|BlockParam $blockParam = BlockParam::PENDING): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getTransactionCount", [$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetBlockTransactionCountByHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetBlockTransactionCountByNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetUncleCountByBlockHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetUncleCountByBlockNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethGetCode(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc(
			"eth_getCode", [$address->toHex(), $this->blockParam($blockParam)]
		)[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSign(Address $address, string $dataHex): string
	{
		return $this->runRpc(
			"eth_sign", [$address->toHex(), $dataHex]
		)[0];
	}

	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethSignTransaction(Transaction $txn, Address $from): string
	{
		return $this->runRpc("eth_signTransaction", [$this->transactionToRPCArr($txn, $from)])[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSendTransaction(Transaction $txn, Address $from): Hash
	{
		try {
			return Hash::fromHex($this->runRpc("eth_sendTransaction", [$this->transactionToRPCArr($txn, $from)])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 * @throws UnexpectedUnsignedException
	 * @throws EthBinderArgumentException
	 */
	public function ethSendRawTransaction(Transaction $signedTransaction): Hash
	{
		if(!$signedTransaction->isSigned())
			throw new UnexpectedUnsignedException();
		try {
			return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$signedTransaction->encodeHex()])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @param string $rawTransactionHex
	 * @return Hash
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethSendRawTransactionHex(string $rawTransactionHex): Hash
	{
		if(!str_starts_with($rawTransactionHex, "0x"))
			$rawTransactionHex = "0x".$rawTransactionHex;
		try {
			return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$rawTransactionHex])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethCall(Transaction $message, ?Address $from = null, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc("eth_call", [$this->transactionToRPCArr($message, $from, true), $this->blockParam($blockParam)])[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethEstimateGas(Transaction $txn, ?Address $from): int
	{
		return hexdec($this->runRpc("eth_estimateGas", [$this->transactionToRPCArr($txn, $from, true)])[0]);
	}

	/**
	 * @param Hash $hash
	 * @param bool $fullBlock
	 * @return Block
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethGetBlockByHash(Hash $hash, bool $fullBlock = false): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getBlockByHash", [$hash->toHex(), $fullBlock]));
		} catch(BadAddressChecksumException|HexBlobNotEvenException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetBlockByNumber(int|BlockParam $blockParam = BlockParam::LATEST, bool $fullBlock = false): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getBlockByNumber", [$this->blockParam($blockParam), $fullBlock]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws NotSupportedException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethGetTransactionByHash(Hash $hash): Transaction
	{
		try {
			return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByHash", [$hash->toHex()]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionByBlockHashAndIndex(Hash $hash, int $index): Transaction
	{
		try {
			return Transaction::fromRPCArr($this->runRpc(
				"eth_getTransactionByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($index)]
			));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionByBlockNumberAndIndex(int|BlockParam $blockParam, int $index): Transaction
	{
		try {
		return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByBlockNumberAndIndex",
			[$this->blockParam($blockParam), "0x".dechex($index)]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetTransactionReceipt(Hash $hash): Receipt
	{
		try {
			return Receipt::fromRPCArr($this->runRpc("eth_getTransactionReceipt", [$hash->toHex()]));
		} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetUncleByBlockHashAndIndex(Hash $hash, int $unclePos): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getUncleByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($unclePos)]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetUncleByBlockNumberAndIndex(int|BlockParam $blockParam, int $unclePos):Block
	{
		try {
			return Block::fromRPCArr(
				$this->runRpc("eth_getUncleByBlockNumberAndIndex", [$this->blockParam($blockParam), "0x".dechex($unclePos)])
			);
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethMaxPriorityFeePerGas(): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_maxPriorityFeePerGas", []
		)[0]);
	}

	/**
	 * Installs event filterer on rpc node and returns ID of the filter. Accepts few types, but bear in mind that string
	 * type is always considered to be binary blob.
	 *
	 * @param int|BlockParam|null $fromBlock
	 * @param int|BlockParam|null $toBlock
	 * @param Address|Address[] $address
	 * @param string|bool|HashSerializable|string[]|bool[]|HashSerializable[] $topic0
	 * @param null|string|bool|HashSerializable|string[]|bool[]|HashSerializable[] $topic1
	 * @param null|string|bool|HashSerializable|string[]|bool[]|HashSerializable[] $topic2
	 * @param null|string|bool|HashSerializable|string[]|bool[]|HashSerializable[] $topic3
	 * @return OOGmp
	 * @throws EthBinderArgumentException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethNewFilter(
		Address|array $address,
		null|int|BlockParam $fromBlock,
		null|int|BlockParam $toBlock,
		string|bool|HashSerializable|array $topic0,
		null|string|bool|HashSerializable|array $topic1 = null,
		null|string|bool|HashSerializable|array $topic2 = null,
		null|string|bool|HashSerializable|array $topic3 = null
	): OOGmp {
		$prms = $this->parseFilterInput($address, $fromBlock, $toBlock, null, $topic0, $topic1, $topic2, $topic3);
		return new OOGmp($this->runRpc("eth_newFilter", [$prms])[0]);
	}

	/**
	 * @param Address|array $address
	 * @param int|BlockParam|null $fromBlock if $blockHash not null, it MUST be null
	 * @param int|BlockParam|null $toBlock if $blockHash not null, it MUST be null
	 * @param Hash|null $blockHash if $fromBlock and/or $toBlock is not null, it MUST be null
	 * @param string|bool|HashSerializable|array $topic0
	 * @param string|bool|HashSerializable|array|null $topic1
	 * @param string|bool|HashSerializable|array|null $topic2
	 * @param string|bool|HashSerializable|array|null $topic3
	 * @return Log[]
	 * @throws BadAddressChecksumException
	 * @throws EthBinderArgumentException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethGetLogs(
		Address|array $address,
		null|int|BlockParam $fromBlock,
		null|int|BlockParam $toBlock,
		null|Hash $blockHash,
		string|bool|HashSerializable|array $topic0,
		null|string|bool|HashSerializable|array $topic1 = null,
		null|string|bool|HashSerializable|array $topic2 = null,
		null|string|bool|HashSerializable|array $topic3 = null
	): array {
		$prms = $this->parseFilterInput($address, $fromBlock, $toBlock, $blockHash, $topic0, $topic1, $topic2, $topic3);
		$o = [];
		foreach($this->runRpc("eth_getLogs", [$prms]) AS $log) {
			$o[] = Log::fromRPCArr($log);
		}
		return $o;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetFilterChanges(OOGmp $filterId): array
	{
		return $this->runRpc("eth_getFilterChanges", [$filterId->toString(true)]);
	}

	/**
	 * @param OOGmp $filterId
	 * @return Log[]
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethGetFilterLogs(OOGmp $filterId): array
	{
		$d = $this->runRpc("eth_getFilterLogs", [$filterId->toString(true)]);
		$o = [];
		foreach($d AS $itm) {
			$o[] = Log::fromRPCArr($itm);
		}
		return $o;
	}

	protected function parseFilterInput(
		Address|array $address,
		null|int|BlockParam $fromBlock,
		null|int|BlockParam $toBlock,
		null|Hash $blockHash,
		string|bool|HashSerializable|array $topic0,
		null|string|bool|HashSerializable|array $topic1 = null,
		null|string|bool|HashSerializable|array $topic2 = null,
		null|string|bool|HashSerializable|array $topic3 = null
	): array
	{
		$prms = [];
		$filters = [null, null, null, null];
		$inputs = [$topic0, $topic1, $topic2, $topic3];

		/**
		 * @throws EthBinderArgumentException
		 */
		$parseTopic = function(string|bool|HashSerializable $topic): string {
			if($topic instanceof HashSerializable)
				return Functions::lPadHex($topic->toHex(), 64, false);
			if(is_string($topic) && strlen($topic) <= 32)
				return Functions::lPadHex("0x".bin2hex($topic), 64, false);
			if($topic === true)
				return "0x".str_repeat("0", 63)."1";
			if($topic === false)
				return "0x".str_repeat("0", 64);
			throw new EthBinderArgumentException("string is too long. Expected 32 byte binary blob.");
		};

		foreach($inputs AS $k => $v) {
			if($v === null)
				continue;
			if(!is_array($v)) {
				$filters[$k] = $parseTopic($v);
				continue;
			}
			$filters[$k] = [];
			foreach($v AS $subV) {
				$filters[$k][] = $parseTopic($subV);
			}
		}

		while (end($filters) === null || end($filters) === []) {
			array_pop($filters);
		}
		$prms["topics"] = $filters;

		if($blockHash === null) {
			if($fromBlock !== null) {
				$prms["fromBlock"] = $this->blockParam($fromBlock);
			}

			if($toBlock !== null) {
				$prms["toBlock"] = $this->blockParam($toBlock);
			}
		} else {
			$prms["blockHash"] = $blockHash->toHex(true);
		}

		if(is_array($address)) {
			$prms["address"] = [];
			foreach($address AS $addr)
				$prms["address"][] = Functions::lPadHex($addr->toHex(true), 64, false);
		} else {
			$prms["address"] = $address->checksummed();
		}
		return $prms;
	}

	private function transactionToRPCArr(Transaction $txn, ?Address $from, bool $asMessage = false): array
	{
		$txData = [];
		if($from === null)
			$from = Address::NULL();
		$txData["from"] = $from->toHex();

		if($txn->to() !== null) {
			$txData["to"] = $txn->to()->toHex();
		}
		if($txn->gasLimit() > 0)
			$txData["gas"] = Functions::int2hex($txn->gasLimit());

		$txData["gasPrice"] = $txn->totalGasPrice()->toString(true);
		$txData["value"] = $txn->value()->toString(true);
		$txData["data"] = $txn->dataHex();
		if($asMessage)
			return $txData;

		$txData["nonce"] = Functions::int2hex($txn->nonce());
		return $txData;
	}
}
