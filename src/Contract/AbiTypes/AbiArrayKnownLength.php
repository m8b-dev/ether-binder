<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

/**
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
class AbiArrayKnownLength extends AbiTuple
{
	protected ?AbstractABIValue $emptyType;

	public function __construct(protected int $length, ?AbstractABIValue $children = null)
	{
		$this->emptyType = $children;
		for($i = 0; $i < $this->length; $i++)
			$this->inner[$i] = clone($children);
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		$ret = "k[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":""). $inr;
		}
		$ret .= "]";
		return $ret;
	}

	/**
	 * @inheritDoc
	 */
	public function unwrapToPhpFriendlyVals(?array $tuplerData): array
	{
		$o = [];
		foreach($this->inner as $item) {
			$o[] = $item->unwrapToPhpFriendlyVals($tuplerData); // arrays unwind "As Is" since bindings flatten tuple arrays.
		}
		return $o;
	}
}
