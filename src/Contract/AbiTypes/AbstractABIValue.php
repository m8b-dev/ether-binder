<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\NotSupportedException;

/**
 * Base class for encoding and decoding ABI (Application Binary Interface) types for Ethereum contracts.
 *
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
abstract class AbstractABIValue
{
	/**
	 * Parse the ABI type and value and returns the corresponding AbstractABIValue object.
	 *
	 * @param string $type The ABI type.
	 * @param mixed $value The value to be parsed.
	 * @return AbstractABIValue An instance of the parsed ABI type.
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 * @throws EthBinderArgumentException
	 */
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
	 * Splits the ABI type into its type and bit-length components. Also works for amount of bytes for non-dynamic bytes.
	 *
	 * @param string $type The ABI type.
	 * @return array<"type"|"bits", string|int|null> The split type and bit-length.
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

	/**
	 * Checks whether the ABI value is dynamic or not.
	 *
	 * @return bool True if dynamic, false otherwise.
	 */
	abstract public function isDynamic(): bool;

	/**
	 * Encodes the ABI value to its binary representation (recursive).
	 *
	 * @return string The binary-encoded string.
	 */
	abstract public function encodeBin(): string;

	/**
	 * Decodes the binary data and updates the object(recursive).
	 *
	 * @param string &$dataBin The binary data to be decoded.
	 * @param int $globalOffset The global offset in the binary data.
	 * @return int The new global offset.
	 */
	abstract public function decodeBin(string &$dataBin, int $globalOffset): int;

	/**
	 * Transforms the ABI value into a more PHP-friendly types, such as Common\Address, OOGmp, and such (recursive).
	 *
	 * @param array|null $tuplerData Data structure for wanted tuples, tightly related to ABIGen logic. Always null-safe.
	 * @return mixed The PHP-friendly value.
	 */
	abstract public function unwrapToPhpFriendlyVals(?array $tuplerData);

	/**
	 * Returns string representation of structure, for known sized arrays with prefix k, for unknown size u,
	 * works recursively. The stringified representation purpose is debugging - to see what data and structure was
	 * created by bindings etc. It's often more useful representation than print_r or var_dump (with or without XDebug).
	 * It's meant to be called on top level tuple, but should work just fine on any part of tree
	 *
	 * @return string stringified structure, for example (u[k[123,0xabcd],k[456,0x1234]],256)
	 */
	abstract public function __toString(): string;
}
