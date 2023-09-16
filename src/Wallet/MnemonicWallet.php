<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

use Elliptic\EC\KeyPair;
use FurqanSiddiqui\BIP39\BIP39;
use FurqanSiddiqui\BIP39\Exception\MnemonicException;
use FurqanSiddiqui\BIP39\Exception\WordListException;
use FurqanSiddiqui\BIP39\Mnemonic;
use FurqanSiddiqui\BIP39\WordList;
use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\MnemonicWalletInternalException;
use M8B\EtherBinder\Exceptions\WrongMnemonicPathException;
use M8B\EtherBinder\Utils\OOGmp;
use SensitiveParameter;

/**
 * MnemonicWallet extends the AbstractWallet to create a wallet from a mnemonic phrase.
 * It handles the derivation and management of keys using BIP39 and HD Wallet standards.
 *
 * @author DubbaThony
 */
class MnemonicWallet extends AbstractWallet
{
	private const offset = 0x80000000;

	/**
	 * Constructor to create a MnemonicWallet from words. While constructing, the words are processed into private key.
	 *
	 * @param string|array $words Mnemonic phrase words.
	 * @param string $passPhrase Optional passphrase for seed generation. For any passphrase it will generate another key,
	 *        and there is no way of knowing if passphrase matched other than checking returning address is what was expected
	 * @param string $path HD Wallet derivation path.
	 * @param MnemonicLanguage|string $language Language for the mnemonic words.
	 * @throws WrongMnemonicPathException
	 * @throws MnemonicWalletInternalException
	 */
	public function __construct(
		#[SensitiveParameter] string|array $words,
		#[SensitiveParameter] string       $passPhrase = "",
		string                             $path = "m/44'/60'/0'/0/0",
	    MnemonicLanguage|string            $language = MnemonicLanguage::ENGLISH
	) {
		if(is_array($words))
			$words = implode(" ", $words);

		if($language instanceof MnemonicLanguage)
			$language = $language->toString();

		try {
			$words = BIP39::Words($words, $language);
			$seed  = (new Mnemonic(WordList::English(), $words->entropy, $words->binaryChunks))
				     ->generateSeed($passPhrase);
			$key   = hex2bin(hash_hmac('sha512', $seed, "Bitcoin seed"));
		} catch(WordListException|MnemonicException $e) {
			throw new MnemonicWalletInternalException($e->getMessage(), $e->getCode(), $e);
		}

		$chainCode = substr($key, 32);
		$privK     = substr($key, 0, 32);
		foreach($this->parsePath($path) AS $childNum)
			list($privK, $chainCode) = $this->deriveChild($privK, $chainCode, $childNum);

		$this->key = Key::fromBin($privK);
	}

	/**
	 * Generates a new mnemonic phrase. If you don't have reason, use defaults.
	 *
	 * @param int $wordCount Number of words in the mnemonic phrase.
	 * @param MnemonicLanguage|string $language Language for the mnemonic words. Defaults to english
	 * @return array List of words for the mnemonic.
	 * @throws MnemonicWalletInternalException
	 */
	public static function genNew(int $wordCount = 24, MnemonicLanguage|string $language = MnemonicLanguage::ENGLISH): array
	{
		if($language instanceof MnemonicLanguage)
			$language = $language->toString();
		try {
			return BIP39::Generate($wordCount, $language)->words;
		} catch(WordListException|MnemonicException $e) {
			throw new MnemonicWalletInternalException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws WrongMnemonicPathException
	 */
	private	function parsePath(string $path): array
	{
		$path = explode("/", $path);
		if(strtolower($path[0]) !== "m")
			throw new WrongMnemonicPathException("bad format");
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

	private function serializeCurvePoint(#[SensitiveParameter] KeyPair $p): string
	{
		$x = hex2bin($p->getPublic()->x->toString(16));
		$y = $p->getPublic()->y;

		return ($y->isOdd() ? "\x03" : "\x02") .
			str_pad($x, 32, "\x0", STR_PAD_LEFT);
	}

	private function deriveChild(#[SensitiveParameter] string $privateKeyBin, #[SensitiveParameter]  string $chainCodeBin, int $childNum): array
	{
		$keyPair = EC::ec()->keyFromPrivate(bin2hex($privateKeyBin));
		if ($childNum >= self::offset) {
			$blob = "\x0" . $privateKeyBin;
		} else {
			$blob = $this->serializeCurvePoint($keyPair);
		}

		$blob   .= hex2bin(str_pad(dechex($childNum), 8, "0", STR_PAD_LEFT));
		$hmacOut = hash_hmac('sha512', $blob, $chainCodeBin, true);
		$l       = substr($hmacOut, 0, 32);
		$r       = substr($hmacOut, 32);

		return [(new OOGmp(bin2hex($l), 16))
			->add(new OOGmp(bin2hex($privateKeyBin), 16))
			->mod(new OOGmp(EC::ec()->n->toString(16), 16))
			->toBin(32), $r];
	}
}
