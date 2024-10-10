<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * Block is a container for Ethereum block and contains various attributes related to it.
 *
 * @author DubbaThony
 */
class Block
{
	public int $number;
	public Hash $hash;
	public Hash $parentHash;
	public BlockNonce $nonce;
	public Hash $sha3Uncles;
	public Bloom $logsBloom;
	public Hash $transactionsRoot;
	public Hash $stateRoot;
	public Hash $receiptsRoot;
	public ?Hash $parentBeaconBlockRoot;
	public Address $miner;
	public OOGmp $difficulty;
	public OOGmp $totalDifficulty;
	public string $extraData;
	public int $size;
	public ?OOGMP $baseFeePerGas;
	public int $gasLimit;
	public int $gasUsed;
	public ?int $blobGasUsed;
	public ?int $excessBlobGas;
	public int $timestamp;
	/** @var Transaction[]|Hash[] */
	public array $transactions;
	/** @var Hash[] */
	public array $uncles;
	/** @var ValidatorWithdrawal[] */
	public array $validatorWithdrawals;
	public ?Hash $validatorWithdrawalsRoot;

	/**
	 * Constructs a Block object from an array received through RPC.
	 *
	 * @param array $rpcArr The array containing block data.
	 * @return static The Block object.
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 * @throws HexBlobNotEvenException
	 */
	public static function fromRPCArr(array $rpcArr): static
	{
		$block = new static();
		$block->number                   = hexdec($rpcArr["number"]);
		$block->hash                     = Hash::fromHex($rpcArr["hash"]);
		$block->parentHash               = Hash::fromHex($rpcArr["parentHash"]);
		$block->nonce                    = BlockNonce::fromHex($rpcArr["nonce"]);
		$block->sha3Uncles               = Hash::fromHex($rpcArr["sha3Uncles"]);
		$block->logsBloom                = Bloom::fromHex($rpcArr["logsBloom"]);
		$block->transactionsRoot         = Hash::fromHex($rpcArr["transactionsRoot"]);
		$block->stateRoot                = Hash::fromHex($rpcArr["stateRoot"]);
		$block->receiptsRoot             = Hash::fromHex($rpcArr["receiptsRoot"]);
		$block->miner                    = Address::fromHex($rpcArr["miner"]);
		$block->difficulty               = new OOGmp($rpcArr["difficulty"] ?? 0, 16);
		$block->totalDifficulty          = new OOGmp($rpcArr["totalDifficulty"] ?? 0, 16);
		$block->extraData                = $rpcArr["extraData"];
		$block->parentBeaconBlockRoot    = isset($rpcArr["parentBeaconBlockRoot"])
											? Hash::fromHex($rpcArr["parentBeaconBlockRoot"]) : null;
		$block->blobGasUsed              = isset($rpcArr["blobGasUsed"]) ? hexdec($rpcArr["blobGasUsed"]) : null;
		$block->excessBlobGas            = isset($rpcArr["excessBlobGas"]) ? hexdec($rpcArr["excessBlobGas"]) : null;
		$block->size                     = hexdec($rpcArr["size"]);
		$block->gasLimit                 = hexdec($rpcArr["gasLimit"]);
		$block->gasUsed                  = hexdec($rpcArr["gasUsed"]);
		$block->timestamp                = hexdec($rpcArr["timestamp"]);
		$block->validatorWithdrawalsRoot = isset($rpcArr["withdrawalsRoot"])
											? Hash::fromHex($rpcArr["withdrawalsRoot"]) : null;

		if(empty($rpcArr["transactions"])) {
			$block->transactions = [];
		} else {
			foreach($rpcArr["transactions"] AS $transaction) {
				if(is_string($transaction)) {
					$block->transactions[] = Hash::fromHex($transaction);
					continue;
				}
				$block->transactions[] = Transaction::fromRPCArr($transaction);
			}
		}
		$block->validatorWithdrawals = [];
		if(!empty($rpcArr["withdrawals"])) {
			foreach($rpcArr["withdrawals"] AS $withdrawal)
				$block->validatorWithdrawals[] = ValidatorWithdrawal::fromRPCArr($withdrawal);
		}
		if(!empty($rpcArr["baseFeePerGas"])) {
			$block->baseFeePerGas = new OOGmp($rpcArr["baseFeePerGas"]);
		} else {
			$block->baseFeePerGas = null;
		}
		$block->uncles           = $rpcArr["uncles"];
		return $block;
	}

	/**
	 * Checks if the block looks like coming from EIP-1559 enabled chain by looking if base fee is defined.
	 *
	 * @return bool True if block is EIP-1559, otherwise false.
	 */
	public function isEIP1559(): bool
	{
		return $this->baseFeePerGas !== null;
	}

	/**
	 * Checks if the block looks like coming from EIP-4844 enabled chain by looking if blob fees are defined
	 * @see https://eips.ethereum.org/EIPS/eip-4844#header-extension
	 *
	 * @return bool
	 */
	public function isEIP4844(): bool
	{
		return $this->blobGasUsed !== null;
	}
}
