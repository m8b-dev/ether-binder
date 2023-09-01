<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

use Elliptic\EC\KeyPair;
use FurqanSiddiqui\BIP39\BIP39;
use FurqanSiddiqui\BIP39\Mnemonic;
use FurqanSiddiqui\BIP39\WordList;
use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\WrongMenemonicPathException;
use M8B\EtherBinder\Utils\OOGmp;

class MnemonicWallet extends AbstractWallet
{
	private const offset = 0x80000000;

	public function __construct(
		#[\SensitiveParameter] string|array $words,
		#[\SensitiveParameter] string $passPhrase = "",
		string $path = "m/44'/60'/0'/0/0",
	    MnemonicLanguage|string $language = MnemonicLanguage::ENGLISH
	) {
		if(is_array($words))
			$words = implode(" ", $words);
		if($language instanceof MnemonicLanguage)
			$language = $language->toString();

		$words = BIP39::Words($words, $language);
		$seed = (new Mnemonic(WordList::English(), $words->entropy, $words->binaryChunks))->generateSeed($passPhrase);
		$key  = hex2bin(hash_hmac('sha512', $seed, "Bitcoin seed"));

		$chainCode = substr($key, 32);;
		$privK     = substr($key, 0, 32);
		foreach($this->parsePath($path) AS $childNum)
			list($privK, $chainCode) = $this->deriveChild($privK, $chainCode, $childNum);

		$this->key = Key::fromBin($privK);
	}

	public static function genNew(int $wordCount = 24, MnemonicLanguage|string $language = MnemonicLanguage::ENGLISH): array
	{
		if($language instanceof MnemonicLanguage)
			$language = $language->toString();
		return BIP39::Generate($wordCount, $language)->words;
	}

	private	function parsePath(string $path): array
	{
		$path = explode("/", $path);
		if(strtolower($path[0]) !== "m")
			throw new WrongMenemonicPathException("bad format");
		$o = [];
		foreach(array_slice($path, 1) AS $itm ) {
			$hardened = str_ends_with($itm, "'");
			$itm      = rtrim($itm, "'");
			$itm      = (int)$itm;
			$itm     += $hardened ? self::offset : 0;
			$o[]      = $itm;
		}
		return $o;
	}

	private function serializeCurvePoint(#[\SensitiveParameter] KeyPair $p): string
	{
		$x = hex2bin($p->getPublic()->x->toString(16));
		$y = $p->getPublic()->y;

		return ($y->isOdd() ? "\x03" : "\x02") .
			str_pad($x, 32, "\x0", STR_PAD_LEFT);
	}

	private function deriveChild(#[\SensitiveParameter] string $privateKeyBin,#[\SensitiveParameter]  string $chainCodeBin, int $childNum): array
	{
		$keyPair = EC::ec()->keyFromPrivate(bin2hex($privateKeyBin));
		if ($childNum >= self::offset) {
			$blob = "\x0" . $privateKeyBin;
		} else {
			$blob = $this->serializeCurvePoint($keyPair);
		}
		$blob .= hex2bin(str_pad(dechex($childNum), 8, "0", STR_PAD_LEFT));

		$hmacOut = hash_hmac('sha512', $blob, $chainCodeBin, true);
		$l = substr($hmacOut, 0, 32);
		$r = substr($hmacOut, 32);

		return [(new OOGmp(bin2hex($l), 16))
			->add(new OOGmp(bin2hex($privateKeyBin), 16))
			->mod(new OOGmp(EC::ec()->n->toString(16), 16))
			->toBin(32), $r];
	}
}
