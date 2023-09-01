<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Utils;

enum EtherFormats
{
	case WEI;
	case KWEI;
	case MWEI;
	case GWEI;
	case SZABO;
	case FINNEY;
	case ETHER;
	case KETHER;
	case METHER;
	case GETHER;
	case TETHER;

	public static function fromString(string $in): ?EtherFormats
	{
		return match(strtolower(trim($in))) {
			"wei"                                  => EtherFormats::WEI,
			"kwei", "babbage", "femtoether"        => EtherFormats::KWEI,
			"mwei", "lovelace", "picoether"        => EtherFormats::MWEI,
			"gwei", "shannon", "nanoether", "nano" => EtherFormats::GWEI,
			"szabo", "microether", "micro"         => EtherFormats::SZABO,
			"finney", "milliether", "milli"        => EtherFormats::FINNEY,
			"ether"                                => EtherFormats::ETHER,
			"kether", "grand"                      => EtherFormats::KETHER,
			"mether"                               => EtherFormats::METHER,
			"gether"                               => EtherFormats::GETHER,
			"tether"                               => EtherFormats::TETHER,
			default                                => null
		};
	}

	public static function fromFactor(int $in)
	{
		return match($in) {
			0       => EtherFormats::WEI,
			3       => EtherFormats::KWEI,
			6       => EtherFormats::MWEI,
			9       => EtherFormats::GWEI,
			12      => EtherFormats::SZABO,
			15      => EtherFormats::FINNEY,
			18      => EtherFormats::ETHER,
			21      => EtherFormats::KETHER,
			24      => EtherFormats::METHER,
			27      => EtherFormats::GETHER,
			30      => EtherFormats::TETHER,
			default => null
		};
	}

	public static function fromAny(int|string|self $in, self $default = self::ETHER): self
	{
		if(is_int($in))
			$r = self::fromFactor($in);
		elseif(is_string($in))
			$r = self::fromString($in);
		else
			return $in;
		if($r == null)
			return $default;
		return $r;
	}

	public function factor(): int
	{
		return match($this) {
			EtherFormats::WEI    => 0,
			EtherFormats::KWEI   => 3,
			EtherFormats::MWEI   => 6,
			EtherFormats::GWEI   => 9,
			EtherFormats::SZABO  => 12,
			EtherFormats::FINNEY => 15,
			EtherFormats::ETHER  => 18,
			EtherFormats::KETHER => 21,
			EtherFormats::METHER => 24,
			EtherFormats::GETHER => 27,
			EtherFormats::TETHER => 30,
		};
	}
}
