<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Utils\OOGmp;

abstract class Net extends Eth
{
	public function netVersion(): int
	{
		return $this->runRpc("net_version")[0];
	}

	public function netListening(): bool
	{
		return $this->runRpc("net_listening")[0];
	}

	public function netPeerCount(): int
	{
		return (new OOGmp($this->runRpc("net_peerCount")[0]))->toInt();
	}
}