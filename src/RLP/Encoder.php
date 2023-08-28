<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RLP;

use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * Encoder of RLP format capable of working with both binary and hex outputs. Internally works on binary.
 *
 * @author DubbaThony
 */
class Encoder
{
	private function __construct()
	{}

	/**
	 * Converts data to RLP
	 * Accepts array (recursively - can contain sub-arrays, sub-sub-arrays, etc.) of 0x-hex strings, ints, Hash (and children) types.
	 * All non-hex-strings will be converted to hex strings as actual strings (ie. "foo" will be 0x666f6f - 102, 111, 111).
	 * Entire input will NOT be wrapped with array, as some might expect. If you require this (ie. legacy transactions), wrap
	 * the input on caller side
	 *
	 * @param array $input data to be encoded
	 * @return string hex representation of encoded data
	 */
	public static function encodeHex(array $input): string
	{
		$bin = self::encodeBin($input);
		return "0x".bin2hex($bin);
	}

	/**
	 * Converts data to RLP
	 * Accepts array (recursively - can contain sub-arrays, sub-sub-arrays, etc.) of 0x-hex strings, ints, Hash (and children) types.
	 * All non-hex-strings will be converted to hex strings as actual strings (ie. "foo" will be 0x666f6f - 102, 111, 111).
	 * Entire input will NOT be wrapped with array, as some might expect. If you require this (ie. legacy transactions), wrap
	 * the input on caller side
	 *
	 * @param array $input data to be encoded
	 * @return string binary blob of encoded data
	 */
	public static function encodeBin(array $input): string
	{
		$output = "";
		foreach($input AS $item) {
			if(is_array($item)) {
				$output .= self::encodeArray($item);
			} elseif(is_string($item)) {
				$output .= self::encodeString($item);
			} elseif($item instanceof Hash) {
				$output .= self::encodeHash($item);
			} elseif($item instanceof OOGmp) {
				$output .= self::encodeOOGmp($item);
			} elseif(is_int($item)) {
				$output .= self::encodeInt($item);
			} elseif($item === null || $item === 0 || $item === false) {
				$output .= pack("C", 0x80);
			} elseif($item === true) {
				$output .= pack("C", 0x01);
			}
		}
		return $output;
	}

	private static function encodeArray(array $input): string
	{
		if(count($input) == 0)
			return pack("C", 0xc0);
		$encoded = static::encodeBin($input);
		$len = strlen($encoded);
		if($len < 56)
			return pack("C", 0xc0+$len).$encoded;
		return self::encodeLength($len, 0xf7).$encoded;
	}

	private static function encodeString(string $input): string
	{
		if(str_starts_with($input, "0x") && (ctype_xdigit(substr($input, 2)) || $input === "0x")) {
			$input = hex2bin(substr(Functions::lPadHex($input, 2), 2));
		}
		return static::encodeBinaryVal($input);
	}

	private static function encodeBinaryVal(string $input): string
	{
		$len = strlen($input);
		if($len == 0 || $len == 1 && ord($input[0]) == 0) {
			return pack("C", 0x80);
		}
		if($len == 1 && ord($input[0]) <= 0x7f) {
			return $input;
		}
		if($len <= 55) {
			return pack("C", strlen($input) + 0x80) . $input;
		}
		return static::encodeLength($len, 0xb7) . $input;
	}

	private static function encodeLength(int $length, int $base): string
	{
		$encodedLen = ltrim(pack("J", $length), "\0");
		return pack("C", strlen($encodedLen) + $base).$encodedLen;
	}

	// Hash is also Bloom, Address, etc.
	private static function encodeHash(Hash $input): string
	{
		return self::encodeBinaryVal($input->toBin());
	}

	private static function encodeOOGmp(OOGmp $input): string
	{
		return self::encodeString(Functions::lPadHex($input->toString(true), 2));
	}

	private static function encodeInt(int $input): string
	{
		return self::encodeString("0x".Functions::lPadHex(dechex($input), 2));
	}
}
