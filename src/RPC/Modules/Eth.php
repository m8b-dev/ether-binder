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
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Exceptions\UnexpectedUnsignedException;
use M8B\EtherBinder\RPC\BlockParam;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

abstract class Eth extends Debug
{
	public function ethProtocolVersion(): int
	{
		return $this->runRpc("eth_protocolVersion")[0];
	}

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

	public function ethCoinbase(): Address
	{
		return Address::fromHex($this->runRpc("eth_coinbase")[0]);
	}

	private ?int $cachedChainId = null;
	public function ethChainID(): int
	{
		if($this->cachedChainId === null)
			$this->cachedChainId = (int)hexdec($this->runRpc("eth_chainId")[0]);
		return $this->cachedChainId;
	}

	public function ethMining(): bool
	{
		return $this->runRpc("eth_mining")[0];
	}

	public function ethHashrate(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_hashrate")[0]);
	}

	public function ethGasPrice(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_gasPrice")[0]);
	}

	/**
	 * @return Address[]
	 */
	public function ethAccounts(): array
	{
		$accountsRaw = $this->runRpc("eth_accounts");
		$return = [];
		foreach($accountsRaw AS $accountRaw) {
			$return[] = Address::fromHex($accountRaw);
		}
		return $return;
	}

	public function ethBlockNumber(): int
	{
		return (new OOGmp($this->runRpc("eth_blockNumber")[0]))->toInt();
	}

	public function ethGetBalance(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getBalance",
			[$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	public function ethGetStorageAt(Address $address, OOGmp $position, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getStorageAt",
			[$address->toHex(true), $position->toString(true), $this->blockParam($blockParam)]
		)[0]);
	}

	public function ethGetTransactionCount(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getTransactionCount", [$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	public function ethGetBlockTransactionCountByHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	public function ethGetBlockTransactionCountByNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	public function ethGetUncleCountByBlockHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	public function ethGetUncleCountByBlockNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	public function ethGetCode(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc(
			"eth_getCode", [$address->toHex(), $this->blockParam($blockParam)]
		)[0];
	}

	public function ethSign(Address $address, string $dataHex): string
	{
		return $this->runRpc(
			"eth_sign", [$address->toHex(), $dataHex]
		)[0];
	}

	public function ethSignTransaction(Transaction $txn, Address $from): string
	{
		return $this->runRpc("eth_signTransaction", [$this->transactionToRPCArr($txn, $from)])[0];
	}

	public function ethSendTransaction(Transaction $txn, Address $from): Hash
	{
		return Hash::fromHex($this->runRpc("eth_sendTransaction", [$this->transactionToRPCArr($txn, $from)])[0]);
	}

	public function ethSendRawTransaction(Transaction $signedTransaction): Hash
	{
		if(!$signedTransaction->isSigned())
			throw new UnexpectedUnsignedException();
		return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$signedTransaction->encodeHex()])[0]);
	}

	public function ethSendRawTransactionHex(string $rawTransactionHex): Hash
	{
		if(!str_starts_with($rawTransactionHex, "0x"))
			$rawTransactionHex = "0x".$rawTransactionHex;
		return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$rawTransactionHex])[0]);
	}

	public function ethCall(Transaction $message, ?Address $from = null, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc("eth_call", [$this->transactionToRPCArr($message, $from, true), $this->blockParam($blockParam)])[0];
	}

	public function ethEstimateGas(Transaction $txn, ?Address $from): int
	{
		return hexdec($this->runRpc("eth_estimateGas", [$this->transactionToRPCArr($txn, $from, true)])[0]);
	}

	public function ethGetBlockByHash(Hash $hash, bool $fullBlock = false): Block
	{
		return Block::fromRPCArr($this->runRpc("eth_getBlockByHash", [$hash->toHex(), $fullBlock]));
	}

	public function ethGetBlockByNumber(int|BlockParam $blockParam = BlockParam::LATEST, bool $fullBlock = false): Block
	{
		return Block::fromRPCArr($this->runRpc("eth_getBlockByNumber", [$this->blockParam($blockParam), $fullBlock]));
	}

	public function ethGetTransactionByHash(Hash $hash): Transaction
	{
		return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByHash", [$hash->toHex()]));
	}

	public function ethGetTransactionByBlockHashAndIndex(Hash $hash, int $index): Transaction
	{
		return Transaction::fromRPCArr($this->runRpc(
			"eth_getTransactionByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($index)]
		));
	}

	public function ethGetTransactionByBlockNumberAndIndex(int|BlockParam $blockParam, int $index): Transaction
	{
		return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByBlockNumberAndIndex",
			[$this->blockParam($blockParam), "0x".dechex($index)]));
	}

	public function ethGetTransactionReceipt(Hash $hash): Receipt
	{
		return Receipt::fromRPCArr($this->runRpc("eth_getTransactionReceipt", [$hash->toHex()]));
	}

	public function ethGetUncleByBlockHashAndIndex(Hash $hash, int $unclePos): Block
	{
		return Block::fromRPCArr($this->runRpc("eth_getUncleByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($unclePos)]));
	}

	public function ethGetUncleByBlockNumberAndIndex(int|BlockParam $blockParam, int $unclePos):Block
	{
		return Block::fromRPCArr(
			$this->runRpc("eth_getUncleByBlockNumberAndIndex", [$this->blockParam($blockParam), "0x".dechex($unclePos)])
		);
	}

	/* todo: add filters */

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
