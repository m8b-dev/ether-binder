<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\RLP\Decoder;
use M8B\EtherBinder\RPC\BlockParam;

abstract class Debug extends AbstractModule
{
	// Surprisingly that's entirety of the debug namespace that's defined in official spec:
	// https://ethereum.github.io/execution-apis/api-documentation/
	// If you are reading this comment, suprised also, you can use
	// $rpc->runRpc("debug_", $prms)
	// For reference most popular is geth, for reference:
	// https://geth.ethereum.org/docs/interacting-with-geth/rpc/ns-debug

	/**
	 * @throws EthBinderLogicException
	 */
	public function debugGetRawBlock(int|BlockParam $blockParam = BlockParam::LATEST): array
	{
		return Decoder::decodeRLPHex(
			$this->runRpc("debug_getRawBlock", [$this->blockParam($blockParam)])[0]
		);
	}

	/**
	 * @throws EthBinderLogicException
	 */
	public function  debugGetRawHeader(int|BlockParam $blockParam = BlockParam::LATEST): array
	{
		return Decoder::decodeRLPHex(
			$this->runRpc("debug_getRawHeader", [$this->blockParam($blockParam)])[0]
		);
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 */
	public function debugGetRawReceipts(int|BlockParam $blockParam = BlockParam::LATEST): array
	{
		$receipts = $this->runRpc("debug_getRawReceipts", [$this->blockParam($blockParam)]);
		$o = [];

		foreach($receipts AS $rcpt)
			try {
				$o[] = Receipt::fromRPCArr($rcpt);
			} catch(BadAddressChecksumException|NotSupportedException|InvalidHexLengthException|InvalidHexException $e) {
				throw new RPCInvalidResponseParamException($e->getMessage(), $e->getCode(), $e);
			}
		return $o;
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 */
	public function debugGetRawTransaction(Hash $h): Transaction
	{
		return Transaction::decodeHex(
			$this->runRpc("debug_getRawTransaction", [$h->toHex()])[0]
		);
	}
}
