<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;

abstract class Web3 extends Net
{
	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function web3ClientVersion(): string
	{
		return $this->runRpc("web3_clientVersion", [])[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function web3Sha3Keccak(string $inputHex): Hash
	{
		try {
			return Hash::fromHex($this->runRpc("eth_sendTransaction", [$inputHex])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function web3Sha3KeccakBin(string $inputBin): Hash
	{
		try {
			$inputHex = "0x" . bin2hex($inputBin);
			return Hash::fromHex($this->runRpc("eth_sendTransaction", [$inputHex])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}
}
