<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

enum BlockParam
{
	/** the latest mined block */
	case LATEST;
	/** the earliest/genesis block */
	case EARLIEST;
	/** the pending state/transactions */
	case PENDING;
	/** the latest safe head block */
	case SAFE;
	/** the latest finalized block */
	case FINALIZED;

	public function toString(): string
	{
		return match($this){
			self::LATEST => "latest",
			self::EARLIEST => "earliest",
			self::PENDING => "pending",
			self::SAFE => "safe",
			self::FINALIZED => "finalized",
		};
	}
}
