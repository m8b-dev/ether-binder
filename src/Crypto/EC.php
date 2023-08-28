<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */


namespace M8B\EtherBinder\Crypto;

use kornrunner\Keccak;
use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Utils\OOGmp;

class EC
{
	private static \Elliptic\EC $ec;

	private static function init(): void
	{
		static $done = false;
		if(!$done)
			self::$ec = new \Elliptic\EC("secp256k1");
		$done = true;
	}

	public static function Recover(Hash $hash, OOGmp $r, OOGmp $s, OOGmp $v): Address
	{
		self::init();

		$pubK = self::$ec->recoverPubKey(
			$hash->toHex(false),
			[
				"r" => $r->toString(true, true),
				"s" => $s->toString(true, true)
			],
			$v->toInt());
		$pubKBin = hex2bin($pubK->encode("hex"));
		$pubKBin = substr($pubKBin, 1);
		$hash = Keccak::hash($pubKBin, 256, true);
		return Address::fromBin(substr($hash, 32 - 20));
	}
}