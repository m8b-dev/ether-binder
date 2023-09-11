<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\NotSupportedException;

abstract class AbstractABIValue
{
	public static function parseValue(string $type, mixed $value): AbstractABIValue
	{
		if(is_array($value))
			throw new EthBinderLogicException("AbstractABIValue::parseValue - provided value that's array. Use AbiArray[Unk|K]nownLength for this");
		if($type === "uint")
			$type = "uint256";
		if($type === "int")
			$type = "int256";

		list("type" => $typeNoBits, "bits" => $bits) = self::splitTypeBits($type);

		switch($typeNoBits) {
			case "uint":
				return new AbiUint($value, $bits);
			case "int":
				return new AbiInt($value, $bits);
			case "bool":
				return new AbiBool($value);
			case "bytes":
				return new AbiBytes($value, $bits);
			case "string":
				return new AbiString($value);
			case "function":
				return new AbiFunction($value);
			case "address":
				return new AbiAddress($value);
		}
		throw new NotSupportedException("type $type is not supported");
	}

	/**
	 * @param string $type
	 * @return array<"type"|"bits", string|int|null>
	 */
	public static function splitTypeBits(string $type): array
	{
		$typeNoBits = match (true) {
			str_starts_with($type, "uint") => "uint",
			str_starts_with($type, "int") => "int",
			str_starts_with($type, "bytes") => "bytes",
			default => $type,
		};
		// empty string defaults to 0
		$bits = (int) substr($type, strlen($typeNoBits));
		return ["type" => $typeNoBits, "bits" => $bits];
	}

	abstract public function isDynamic(): bool;
	abstract public function encodeBin(): string;
	abstract public function decodeBin(string $dataBin);
}
