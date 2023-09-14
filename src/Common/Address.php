<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;

/**
 * Address is a class for representing and manipulating Ethereum addresses.
 *
 * @author DubbaThony
 */
class Address extends Hash
{
	protected const dataSizeBytes = 20;

	/**
	 * Initializes from a hexadecimal string. Validates checksum and throws a BadAddressChecksumException when it
	 * does not match. If entire address is upper or lower case, the checksum is ommited.
	 *
	 * @param string $hex Hexadecimal representation of the address.
	 * @return static The Address object.
	 * @throws BadAddressChecksumException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 */
	public static function fromHex(string $hex): static
	{
		if(!self::testChecksum($hex))
			throw new BadAddressChecksumException($hex, self::checksum($hex));
		return parent::fromHex($hex);
	}

	/**
	 * Returns checksummed hex representation of the address.
	 *
	 * @return string Checksummed address.
	 * @throws EthBinderLogicException
	 */
	public function checksummed(): string
	{
		return "0x".self::checksum($this->toHex(false));
	}

	/**
	 * @throws EthBinderLogicException
	 */
	public function __toString(): string
	{
		return $this->checksummed();
	}

	/**
	 * Validates the checksum of a hexadecimal address. If all characters are upper or lower case, it skips the check
	 * and returns true.
	 *
	 * @param string $hexAddr Hexadecimal address to test.
	 * @return bool True if valid, false otherwise.
	 * @throws EthBinderLogicException
	 */
	public static function testChecksum(string $hexAddr): bool
	{
		if(strtolower($hexAddr) == $hexAddr || strtoupper($hexAddr) == $hexAddr)
			return true;
		if(str_starts_with($hexAddr, "0x"))
			$hexAddr = substr($hexAddr, 2);
		return $hexAddr == self::checksum($hexAddr);
	}

	/**
	 * @throws EthBinderLogicException
	 */
	private static function checksum(string $hexAddr): string
	{
		$hexAddr = strtolower($hexAddr);
		// see https://eips.ethereum.org/EIPS/eip-55#specification
		$checksummedBuffer = "";
		try {
			$hashedAddress = Keccak::hash($hexAddr, 256);
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}

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
