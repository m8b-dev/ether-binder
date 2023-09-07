<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

use M8B\EtherBinder\Common\LondonTransaction;
use M8B\EtherBinder\RPC\Modules\Web3;
use M8B\EtherBinder\Utils\EtherFormats;
use M8B\EtherBinder\Utils\OOGmp;
use M8B\EtherBinder\Utils\WeiFormatter;

/**
 * Compound adds elements for useful queries done to RPC with some processing - for example getting avarage of fee tip
 * in blocks range
 */
abstract class Compound extends Web3
{
	const DEFAULT_TIP_GWEI = 1;
	private ?bool $seemsLondon = null;

	public function calcAvgTip(int $blockNumbers = 3): OOGmp
	{
		$blockNum = $this->ethBlockNumber();
		$tips = [];
		for($i = 0; $i < $blockNumbers; $i++) {
			$block = $this->ethGetBlockByNumber($blockNum - $i, true);
			if($this->seemsLondon === null) {
				$this->seemsLondon = $block->isEIP1559();
			}
			foreach($block->transactions AS $transaction) {
				if($transaction instanceof LondonTransaction)
					$tips[] = $transaction->getGasFeeTip();
			}
		}
		if(empty($tips))
			return WeiFormatter::toWei(self::DEFAULT_TIP_GWEI, EtherFormats::GWEI);
		$tmp = new OOGmp(0);
		foreach($tips AS $tip) {
			$tmp = $tmp->add($tip);
		}

		return $tmp->div(count($tips));
	}

	public function isLookingLikeLondon(): bool
	{
		if($this->seemsLondon !== null)
			return $this->seemsLondon;
		$this->seemsLondon = $this->ethGetBlockByNumber()->isEIP1559();
		return $this->seemsLondon;
	}
}