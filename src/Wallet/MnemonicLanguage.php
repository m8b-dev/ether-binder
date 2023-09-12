<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

/**
 * MnemonicLanguage defines supported languages for mnemonic phrases. It is used for instantiating mnemonic wallet
 * if language is different from default english.
 *
 * @author DubbaThony
 */
enum MnemonicLanguage
{
	case ENGLISH;
	case FRENCH;
	case ITALIAN;
	case SPANISH;

	/**
	 * Returns the string representation of the language.
	 *
	 * @return string The language as a lowercase string.
	 */
	public function toString(): string
	{
		return match($this) {
			self::ENGLISH => "english",
			self::FRENCH  => "french",
			self::ITALIAN => "italian",
			self::SPANISH => "spanish",
		};
	}
}
