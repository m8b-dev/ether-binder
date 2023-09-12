<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

/**
 * Bloom is a class for storing Ethereum block log bloom.
 * It consists of 256 bytes.
 *
 * @author DubbaThony
 */
class Bloom extends Hash
{
	protected const dataSizeBytes = 256;
}
