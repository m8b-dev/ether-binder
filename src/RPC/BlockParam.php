<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

/**
 * BlockParam defines constants to represent types of Ethereum blocks.
 *
 * @author DubbaThony
 */
enum BlockParam
{
	/** The latest mined block */
	case LATEST;
	/** The earliest/genesis block */
	case EARLIEST;
	/** The pending state/transactions */
	case PENDING;
	/** The latest safe head block */
	case SAFE;
	/** The latest finalized block */
	case FINALIZED;

	/**
	 * Converts enum case to string compatible with JSON RPC.
	 *
	 * @return string String representation of the enum case.
	 */
	public function toString(): string
	{
		return match($this){
			self::LATEST    => "latest",
			self::EARLIEST  => "earliest",
			self::PENDING   => "pending",
			self::SAFE      => "safe",
			self::FINALIZED => "finalized",
		};
	}
}
