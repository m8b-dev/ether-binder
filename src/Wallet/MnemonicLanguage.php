<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

enum MnemonicLanguage
{
	case ENGLISH;
	case FRENCH;
	case ITALIAN;
	case SPANISH;

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
