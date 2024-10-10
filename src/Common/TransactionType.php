<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\NotSupportedException;

/**
 * TransactionType defines various types of Ethereum transactions.
 *
 * @author DubbaThony
 */
enum TransactionType
{
	case LEGACY;
	case ACCESS_LIST;
	case DYNAMIC_FEE;
	case BLOB;

	/**
	 * Converts a numeric representation of a transaction type, commonly found as first entry in RLP for non-legacy
	 * transaction types, to its corresponding enum value. For example, it can be used for decoding transaction after
	 * reading the RPL - for selecting transaction type object.
	 *
	 * @param string|int $type The numeric representation, it can be integer or hex string, with or without 0x prefix or lpad 2
	 * @return TransactionType The corresponding enum value.
	 * @throws NotSupportedException If the provided type is not recognized, malformed, or not supported.
	 */
	public static function numericToEnum(string|int $type): TransactionType
	{
		return match ($type) {
			0, "0x0", "0x00", "0", "00", chr(0x00) => self::LEGACY,
			1, "0x1", "0x01", "1", "01", chr(0x01) => self::ACCESS_LIST,
			2, "0x2", "0x02", "2", "02", chr(0x02) => self::DYNAMIC_FEE,
			3, "0x3", "0x03", "3", "03", chr(0x03) => self::BLOB,
			default => throw new NotSupportedException("transaction type '$type' is not supported")
		};
	}

	/**
	 * Instantiates a Transaction object of type based on the transaction type.
	 *
	 * @return Transaction Concrete object of abstract Transaction.
	 * @throws NotSupportedException If the transaction type is not supported.
	 */
	public function spawnSuchTransaction(): Transaction
	{
		return match ($this) {
			self::LEGACY      => new LegacyTransaction(),
			self::ACCESS_LIST => new AccessListTransaction(),
			self::DYNAMIC_FEE => new LondonTransaction(),
			self::BLOB        => new CancunTransaction(),
		};
	}

	/**
	 * Returns a byte representing the transaction type commonly found in for example RLP for modern transactions.
	 *
	 * @return string The byte.
	 */
	public function toTypeByte(): string
	{
		return match ($this) {
			self::LEGACY      => chr(0x00),
			self::ACCESS_LIST => chr(0x01),
			self::DYNAMIC_FEE => chr(0x02),
			self::BLOB        => chr(0x03),
		};
	}

	/**
	 * Returns an int representing the transaction type.
	 *
	 * @return int The int.
	 */
	public function toInt(): int
	{
		return match ($this) {
			self::LEGACY      => 0x00,
			self::ACCESS_LIST => 0x01,
			self::DYNAMIC_FEE => 0x02,
			self::BLOB        => 0x03,
		};
	}
}
