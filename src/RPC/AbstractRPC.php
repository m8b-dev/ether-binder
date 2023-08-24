<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\RPC\Modules\Web3;

abstract class AbstractRPC extends Web3
{
	abstract public function raw(string $method, ?array $params = null): array;

	/**
	 * Differs from raw as it doesn't return RPC stuff and returns just data.
	 * @return array result part of output. If output isn't array, it's wrapped by array and sits on key 0.
	 */
	public function runRpc(string $method, ?array $params = null): array
	{
		$d = $this->raw($method, $params);
		if(!empty($d["error"])) {
			$err = $d["error"];
			if($err["code"] === -32601) {
				throw new RPCNotFoundException($err["message"] ?? "method $method not found", $err["code"]);
			} else {
				throw new RPCGeneralException($err["message"] ?? "got error on rpc", $d["code"] ?? 0);
			}
		}
		if(!isset($d["result"])) {
			throw new RPCInvalidResponseParamException("missing result field");
		}
		$res = $d["result"];
		if(is_array($res))
			return $res;
		return [$res];
	}
}