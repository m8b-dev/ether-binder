<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

/**
 * EIP1559Config for base fee estimator. Not full chain config
 * @see Functions::getNextBlockBaseFee()
 * @internal
 */
class EIP1559Config
{
	public const ELASTICITY_MULTIPLIER = 2;
	public const BASE_FEE_CHANGE_DENOMINATOR = 8;
	public const INITIAL_BASE_FEE = 1000000000;

	public int $activationBlockNumber;

	public static function mainnet(): self
	{
		$s = new self();
		$s->activationBlockNumber = 12965000;
		return $s;
	}

	public static function sepolia(): self
	{
		$s = new self();
		$s->activationBlockNumber = 0;
		return $s;
	}

	public static function rinkeby(): self
	{
		$s = new self();
		$s->activationBlockNumber = 8897988;
		return $s;
	}

	public static function goreli(): self
	{
		$s = new self();
		$s->activationBlockNumber = 5062605;
		return $s;
	}
}
