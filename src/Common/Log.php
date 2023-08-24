<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use kornrunner\Keccak;

class Log
{
	public Address $address;
	/** @var Hash[] $topics */
	public array $topics;
	/** @var Hash[] $topics */
	public array $data;

	public int $blockNumber;
	public Hash $transactionHash;
	public int $transactionIndex;
	public Hash $blockHash;
	public int $logIndex;
	public bool $removed;

	public static function fromRPCArr(array $rpcArr): static
	{
		$static = new static();

		$static->address          = Address::fromHex($rpcArr["address"]);
		$static->topics           = [];
		$static->data             = [];
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

		if(str_starts_with($rpcArr["data"], "0x"))
			$data = hex2bin(substr($rpcArr["data"], 2));
		else
			$data = hex2bin($rpcArr["data"]);
		if(strlen($data) == 0)
			return $static;
		foreach(str_split($data, 32) AS $unindexedParam) {
			$static->data[] = Hash::fromBin($unindexedParam);
		}

		return $static;
	}

	public function isSignature(string $eventSignature): bool
	{
		if(empty($this->topics)) throw new \RuntimeException("cannot test log signature on empty object");
		return strtolower($this->topics[0]->toHex(false)) == strtolower(Keccak::hash($eventSignature, 256));
	}
}
