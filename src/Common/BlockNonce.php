<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

/**
 * BlockNonce represents Ethereum block nonces.
 * It consists of 8 bytes.
 *
 * @author DubbaThony
 */
class BlockNonce extends Hash
{
	protected const dataSizeBytes = 8;
}
