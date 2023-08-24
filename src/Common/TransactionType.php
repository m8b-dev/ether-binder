<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\NotSupportedException;

enum TransactionType
{
	case LEGACY;
	case ACCESS_LIST;
	case DYNAMIC_FEE;
	case BLOB;

	public static function numericToEnum(string|int $type): static
	{
		return match ($type) {
			0, "0x0", "0x00" => self::LEGACY,
			1, "0x1", "0x01" => self::ACCESS_LIST,
			2, "0x2", "0x02" => self::DYNAMIC_FEE,
			3, "0x3", "0x03" => self::BLOB,
			default => throw new NotSupportedException("transaction type '$type' is not supported")
		};
	}

	public function spawnSuchTransaction(): Transaction
	{
		return match ($this) {
			self::LEGACY      => new LegacyTransaction(),
			self::ACCESS_LIST => throw new NotSupportedException("transaction access list type is not supported yet"),
			self::DYNAMIC_FEE => new LondonTransaction(),
			self::BLOB        => throw new NotSupportedException("transaction blob type is not supported yet"),
		};
	}
}
