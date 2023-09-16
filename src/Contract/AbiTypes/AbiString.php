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
class AbiString extends AbiBytes
{
	public function __construct(?string $data)
	{
		parent::__construct($data, 0);
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		return $this->data;
	}
}
