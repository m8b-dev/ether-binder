<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;

abstract class AbstractContract
{
	protected AbstractRPC $rpc;

	public function __construct(AbstractRPC $rpc) {

	}

	protected function mkCall(string $signature, array $params): mixed
	{}

	protected function mkTxn(string $signature, array $params): Transaction
	{}

	protected function mkPayableTxn(string $signature, OOGmp $value, array $params): Transaction
	{

	}

	protected function expectBinarySizeNormalizeString(string $binOrHex, int $length): string
	{
		if(strlen($binOrHex) == $length)
			return $binOrHex;
		// if does not start with 0x, but is valid hex, and length is 2* bin length, accept and cast to bin
		if(!str_starts_with($binOrHex, "0x") && ctype_xdigit($binOrHex) && strlen($binOrHex) == 2*$length)
			return hex2bin($binOrHex);
		// if starts with 0x and same as before
		if(str_starts_with($binOrHex, "0x") && ctype_xdigit(substr($binOrHex, 2)) && strlen($binOrHex) == 2+2*$length)
			return hex2bin(substr($binOrHex, 2));
		throw new \InvalidArgumentException("parameter isn't valid bytes$length");
	}

	protected function expectIntOfSize(bool $unsigned, int|OOGmp $value, int $bits): OOGmp
	{
		if(is_int($value))
			$value = new OOGmp($value);

		if($unsigned && $value->lt(0))
			throw new \InvalidArgumentException("parameter value is expected to be unsigned, but got value lower than 0");
		$actualBits = strlen(gmp_strval($value->raw(), 2));
		if($actualBits > $bits) {
			throw new \InvalidArgumentException("parameter value exceeded allowed amount of bits, provided value"
				." requires at least ".($unsigned ? "uint " : "int").$actualBits
				." but underlying is ".($unsigned ? "uint " : "int").$bits);
		}
		return $value;
	}

	protected function expectIntArrOfSize(bool $unsigned, array $value, int $bits): array
	{

	}

	abstract static function abi(): string;
	abstract static function bytecode(): ?string;
}
