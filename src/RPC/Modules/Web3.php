<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Hash;

abstract class Web3 extends Net
{
	public function web3ClientVersion(): string
	{
		return $this->runRpc("web3_clientVersion", [])[0];
	}

	public function web3Sha3Keccak(string $inputHex): Hash
	{
		return Hash::fromHex($this->runRpc("eth_sendTransaction", [$inputHex])[0]);
	}

	public function web3Sha3KeccakBin(string $inputBin): Hash
	{
		$inputHex = "0x" . bin2hex($inputBin);
		return Hash::fromHex($this->runRpc("eth_sendTransaction", [$inputHex])[0]);
	}
}
