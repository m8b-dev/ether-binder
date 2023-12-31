<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * Receipt represents the outcome of a transaction, including logs, hash, deployed contract address, gas information,
 * and other information.
 *
 * @author DubbaThony
 */
class Receipt
{
	public Hash $transactionHash;
	public int $transactionIndex;
	public Hash $blockHash;
	public int $blockNumber;
	public Address $from;
	public ?Address $to = null;
	public OOGmp $cumulativeGasUsed;
	public OOGmp $effectiveGasPrice;
	public int $gasUsed;
	public ?Address $contractAddress = null;
	/** @var Log[] */
	public array $logs = [];
	public Bloom $logsBloom;
	public TransactionType $type;
	public bool $status;

	/**
	 * Constructs a Receipt object from an array received through RPC.
	 *
	 * @param array $rpcArr The array containing receipt data.
	 * @return static The Receipt object.
	 * @throws NotSupportedException when the RPC server doesn't support inferring status from the root.
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 */
	public static function fromRPCArr(array $rpcArr): static
	{
		$static = new static();
		$static->transactionHash   = Hash::fromHex($rpcArr["transactionHash"]);
		$static->transactionIndex  = hexdec($rpcArr["transactionIndex"]);
		$static->blockHash         = Hash::fromHex($rpcArr["blockHash"]);
		$static->blockNumber       = hexdec($rpcArr["blockNumber"]);
		$static->from              = Address::fromHex($rpcArr["from"]);
		$static->cumulativeGasUsed = new OOGmp($rpcArr["cumulativeGasUsed"]);
		$static->effectiveGasPrice = new OOGmp($rpcArr["effectiveGasPrice"]);
		$static->gasUsed           = hexdec($rpcArr["gasUsed"]);
		$static->logsBloom         = Bloom::fromHex($rpcArr["logsBloom"]);
		$static->type              = TransactionType::numericToEnum($rpcArr["type"]);
		if(!isset($rpcArr["status"])) throw new NotSupportedException("inferring status from root is not supported. Try different RPC server.");
		$static->status            = hexdec($rpcArr["status"]) == 1;

		foreach($rpcArr["logs"] AS $rpcLog)
			$static->logs[] = Log::fromRPCArr($rpcLog);

		if(!empty($rpcArr["contractAddress"]))
			$static->contractAddress = Address::fromHex($rpcArr["contractAddress"]);

		if(!empty($rpcArr["to"]))
			$static->to = Address::fromHex($rpcArr["to"]);
		return $static;
	}
}
