<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Block;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\RPC\BlockParam;

/**
 * AbstractModule serves as the base class for specific modules, enabling them to access the runRpc() method.
 * Note that only the first module in the inheritance chain will inherit this, as PHP allows single inheritance only.
 *
 * @author DubbaThony
 */
abstract class AbstractModule
{
	/**
	 * Executes an RPC call with the given method and parameters.
	 *
	 * @param string $method the RPC method name
	 * @param array|null $params optional array of parameters for the RPC call
	 * @return array result of the RPC call
	 * @return array 'result' field of the RPC response. If the result is not an array, it's wrapped in an array under key 0.
	 * @throws RPCGeneralException if any unexpected error is present in RPC response
	 * @throws RPCInvalidResponseParamException if the 'result' field is missing in the response
	 * @throws RPCNotFoundException if the method is not found
	 */
	abstract public function runRpc(string $method, ?array $params = null): array;

	/**
	 * Normalizes a block number parameter for RPC calls.
	 *
	 * @param int|BlockParam $blockNumber the block number or a BlockParam object
	 * @return string hexadecimal representation of the block number or RPC alias string, such as "latest"
	 */
	protected function blockParam(int|BlockParam $blockNumber): string
	{
		if(!is_int($blockNumber)) {
			return $blockNumber->toString();
		} else {
			return "0x".dechex($blockNumber);
		}
	}

	/**
	 *  Either extracts block hash from block and returns it as hex string, or returns hash hex string. Used for
	 *    parameter normalization in RPC calls, for convenience of API.
	 *
	 *  @param Hash|Block $block the block object or a Hash object
	 *  @return string hexadecimal representation of the block hash
	 */
	protected function blockHash(Hash|Block $block): string
	{
		if($block::class == Block::class) {
			return $block->hash->toHex();
		}
		return $block->toHex();
	}
}
