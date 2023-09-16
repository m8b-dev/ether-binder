<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Utils;

/**
 * WeiFormatter class for parsing back and forth human-readable format with specific denominations of ether,
 * most commonly used for WEI.
 *
 * @author DubbaThony
 */
class WeiFormatter
{

	/**
	 * Converts a value from human format.
	 *
	 * @param float|int|string|OOGmp $human The input human value.
	 * @param int|string|EtherFormats $format The input format.
	 * @return OOGmp The value in Wei.
	 */
	public static function fromHuman(float|int|string|OOGmp $human, int|string|EtherFormats $format = EtherFormats::ETHER): OOGmp
	{
		return new OOGmp(
			str_replace(".", "",
				self::normalizeNumberString($human, EtherFormats::fromAny($format)->factor())
			));
	}

	/**
	 * Converts Wei to another format.
	 *
	 * @param int|string|OOGmp $wei The input value in Wei.
	 * @param int $finalDecimals The final decimal places to keep.
	 * @param int|string|EtherFormats $format The output format.
	 * @return string The converted value.
	 */
	public static function fromWei(
		int|string|OOGmp $wei, int $finalDecimals, int|string|EtherFormats $format = EtherFormats::ETHER): string
	{
		$dec = EtherFormats::fromAny($format)->factor();
		$wei = self::normalizeNumberString($wei, 0);
		$len = strlen($wei);
		if ($len <= $dec) {
			$integrals = "0";
			$decimals  = ($len < $dec ? str_repeat("0", $dec-$len) : "").$wei;
		} else {
			$integrals = substr($wei, 0, $len - $dec);
			$decimals  = substr($wei, $len - $dec);
		}
		$correctVal = $integrals.".".$decimals;
		return self::normalizeNumberString($correctVal, $finalDecimals);
	}

	private static function normalizeNumberString(float|int|string|OOGmp $value, int $decimals): string
	{
		switch(true) {
			case is_int($value):
				$value = ((string)$value) . ".0";
				break;
			case is_float($value):
				$value = (string)$value;
				break;
			case is_string($value):
				// if contains undesired characters
				$whitelistedCharsValue = "";
				foreach(str_split($value) AS $char)
					if(ctype_digit($char) || $char == "." || $char == ",")
						$whitelistedCharsValue .= $char;
				$value = $whitelistedCharsValue;

				// if someone puts in spaces for readability, ie 10 000
				$value = str_replace(" ", "", $value);
				// if someone uses , instead of .
				$value = str_replace(",", ".", $value);
				// replace all "." if there is more than one
				if(substr_count($value, ".")??0 > 1) {
					$parts = explode('.', $value);
					$last = array_pop($parts);
					$value = implode('', $parts) . '.' . $last;
				}
				// if there is no "." point, add it
				if(!str_contains($value, "."))
					$value .= ".0";
				break;
			case $value::class == OOGmp::class:
				$value = $value->toString().".0";
		}

		// at this point we have normalized string, but decimals aren't respected, yet
		list($integrals, $valDecimals) = explode(".", $value);
		if($decimals == 0)
			return $integrals;

		$decimalsTrim0 = false;
		if($decimals < 0) {
			$decimalsTrim0 = true;
			$decimals      = abs($decimals);
		}
		if(strlen($valDecimals) > $decimals) {
			$valDecimals = substr($valDecimals, 0, $decimals);
		} elseif(strlen($valDecimals) < $decimals) {
			$valDecimals .= str_repeat("0", $decimals - strlen($valDecimals));
		}
		if($decimalsTrim0)
			$valDecimals = rtrim($valDecimals, "0");

		if($valDecimals == "")
			return $integrals;

		return $integrals.".".$valDecimals;
	}
}
