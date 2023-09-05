<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;


class Address extends Hash
{
	protected const dataSizeBytes = 20;

	public static function fromHex(string $hex): static
	{
		if(!self::testChecksum($hex))
			throw new BadAddressChecksumException($hex, self::checksum($hex));
		return parent::fromHex($hex);
	}

	public function checksummed(): string
	{
		return "0x".self::checksum($this->toHex(false));
	}

	public function __toString(): string
	{
		return $this->checksummed();
	}

	public static function testChecksum(string $hexAddr): bool
	{
		if(strtolower($hexAddr) == $hexAddr || strtoupper($hexAddr) == $hexAddr)
			return true;
		if(str_starts_with($hexAddr, "0x"))
			$hexAddr = substr($hexAddr, 2);
		return $hexAddr == self::checksum($hexAddr);
	}

	private static function checksum(string $hexAddr): string
	{
		$hexAddr = strtolower($hexAddr);
		// see https://eips.ethereum.org/EIPS/eip-55#specification
		$checksummedBuffer = "";
		$hashedAddress = Keccak::hash($hexAddr, 256);

		foreach(str_split($hexAddr) AS $numbleIndex => $character) {
			if(ctype_digit($character)) {
				$checksummedBuffer .= $character;
				continue;
			}
			$hashedAddressNimble = (int)hexdec($hashedAddress[$numbleIndex]);
			if($hashedAddressNimble > 7)
				$checksummedBuffer .= strtoupper($character);
			else
				$checksummedBuffer .= $character;
		}

		return $checksummedBuffer;
	}
}
