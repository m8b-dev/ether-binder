<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Utils\OOGmp;

class ValidatorWithdrawal
{
	public Address $address;
	public OOGmp $amount;
	public OOGmp $validatorIndex;
	public OOGmp $index;

	public static function fromRPCArr(array $rpcArr): static
	{
		$s = new static();
		$s->address        = Address::fromHex($rpcArr["address"]);
		$s->amount         = new OOGmp($rpcArr["amount"]);
		$s->validatorIndex = new OOGmp($rpcArr["validatorIndex"]);
		$s->index          = new OOGmp($rpcArr["index"]);
		return $s;
	}
}