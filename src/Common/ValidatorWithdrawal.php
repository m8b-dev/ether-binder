<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * ValidatorWithdrawal represents a withdrawal event from a validator, including the amount and related indexes.
 *
 * @author DubbaThony
 */
class ValidatorWithdrawal
{
	public Address $address;
	public OOGmp $amount;
	public OOGmp $validatorIndex;
	public OOGmp $index;

	/**
	 * Constructs a ValidatorWithdrawal object from an array received through RPC.
	 *
	 * @param array $rpcArr The array containing withdrawal data.
	 * @return static The ValidatorWithdrawal object.
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 */
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
