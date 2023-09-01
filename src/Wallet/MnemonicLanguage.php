<?php

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
