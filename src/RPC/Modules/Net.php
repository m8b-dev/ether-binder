<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\Utils\OOGmp;

abstract class Net extends Eth
{
	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function netVersion(): int
	{
		return $this->runRpc("net_version")[0];
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function netListening(): bool
	{
		return $this->runRpc("net_listening")[0];
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws EthBinderRuntimeException
	 */
	public function netPeerCount(): int
	{
		return (new OOGmp($this->runRpc("net_peerCount")[0]))->toInt();
	}
}
