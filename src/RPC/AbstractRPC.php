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
/**
 * AbstractRPC provides the base functionality for communicating with Ethereum's JSON-RPC API.
 *
 * @author DubbaThony
 */
abstract class AbstractRPC extends Compound
{
	/**
	 * Sends a raw RPC request using underlying protocol.
	 *
	 * @param string $method the RPC method to call
	 * @param array|null $params optional parameters for the method
	 * @return array full body of RPC response as an array
	 */
	abstract public function raw(string $method, ?array $params = null): array;

	/**
	 * Sends an RPC request and returns only the 'result' data.
	 * @param string $method the RPC method to call
	 * @param array|null $params optional parameters for the method
	 * @return array 'result' field of the RPC response. If the result is not an array, it's wrapped in an array under key 0.
	 * @throws RPCGeneralException if any unexpected error is present in RPC response
	 * @throws RPCInvalidResponseParamException if the 'result' field is missing in the response
	 * @throws RPCNotFoundException if the method is not found
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
