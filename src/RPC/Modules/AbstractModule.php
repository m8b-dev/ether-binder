<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Block;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\RPC\BlockParam;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * Abstract module allows specific modules to access runRpc() method.
 * Only first module will inherit it since PHP supports only one inheritance per class.
 */
abstract class AbstractModule
{
	abstract public function runRpc(string $method, ?array $params = null): array;
	protected function blockParam(int|BlockParam $blockNumber): string
	{
		if(!is_int($blockNumber)) {
			return $blockNumber->toString();
		} else {
			return "0x".dechex($blockNumber);
		}
	}

	protected function blockHash(Hash|Block $block): string
	{
		if($block::class == Block::class) {
			return $block->hash->toHex();
		}
		return $block->toHex();
	}
}
