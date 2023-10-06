<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Utils\Functions;

/**
 * Log represents an Ethereum log entry, emitted by smart contracts.
 *
 * @author DubbaThony
 */
class Log
{
	public Address $address;
	/** @var Hash[] $topics */
	public array $topics;
	public string $data;

	public int $blockNumber;
	public Hash $transactionHash;
	public int $transactionIndex;
	public Hash $blockHash;
	public int $logIndex;
	public bool $removed;

	/**
	 * Constructs a Log object from an array received through RPC.
	 *
	 * @param array $rpcArr The array containing log data.
	 * @return static The Log object.
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 */
	public static function fromRPCArr(array $rpcArr): static
	{
		$static = new static();

		$static->address          = Address::fromHex($rpcArr["address"]);
		$static->topics           = [];
		$static->data             = "";
		$static->blockNumber      = hexdec($rpcArr["blockNumber"]);
		$static->transactionHash  = Hash::fromHex($rpcArr["transactionHash"]);
		$static->transactionIndex = hexdec($rpcArr["transactionIndex"]);
		$static->blockHash        = Hash::fromHex($rpcArr["blockHash"]);
		$static->logIndex         = hexdec($rpcArr["logIndex"]);
		$static->removed          = $rpcArr["removed"];

		foreach($rpcArr["topics"] AS $topic)
			$static->topics[] = Hash::fromHex($topic);

		// data is one big blob of 32-bytes segments (aka hash) representing unindexed params. For future convinience
		//  we will split it

		$data = Functions::hex2bin($rpcArr["data"]);
		$static->data = $data;
		return $static;
	}

	/**
	 * Checks if the log matches the given event signature.
	 *
	 * @param string $eventSignature The event signature to compare.
	 * @return bool True if matching, otherwise false.
	 * @throws EthBinderRuntimeException when topics array is empty.
	 * @throws EthBinderLogicException
	 */
	public function isSignature(string $eventSignature): bool
	{
		if(empty($this->topics))
			throw new EthBinderRuntimeException("cannot test log signature on empty object");
		try {
			return strtolower($this->topics[0]->toHex(false)) == strtolower(Keccak::hash($eventSignature, 256));
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
	}
}
