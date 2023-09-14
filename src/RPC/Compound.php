<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

use M8B\EtherBinder\Common\LondonTransaction;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\RPC\Modules\Web3;
use M8B\EtherBinder\Utils\EtherFormats;
use M8B\EtherBinder\Utils\OOGmp;
use M8B\EtherBinder\Utils\WeiFormatter;

/**
 * Compound adds elements for useful queries done to RPC with some processing - for example getting avarage of fee tip
 * in blocks range
 *
 * @author DubbaThony
 */
abstract class Compound extends Web3
{
	const DEFAULT_TIP_GWEI = 1;
	private ?bool $seemsLondon = null;

	/**
	 * Calculates the average EIP1559 tip over a range of blocks. If the underlying chain doesn't look like EIP1559
	 * chain, it returns fallback of 1 GWEI.
	 *
	 * @param int $blockNumbers number of recent blocks to consider
	 * @return OOGmp average tip across considered blocks
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 */
	public function calcAvgTip(int $blockNumbers = 3): OOGmp
	{
		$blockNum = $this->ethBlockNumber();
		$tips = [];
		if($this->seemsLondon === false)
			return WeiFormatter::fromHuman(1, EtherFormats::GWEI);
		for($i = 0; $i < $blockNumbers; $i++) {
			$block = $this->ethGetBlockByNumber($blockNum - $i, true);
			if($this->seemsLondon === null) {
				$this->seemsLondon = $block->isEIP1559();
				if($this->seemsLondon) {
					$tips = [];
					break;
				}
			}
			foreach($block->transactions AS $transaction) {
				if($transaction instanceof LondonTransaction)
					$tips[] = $transaction->getGasFeeTip();
			}
		}
		if(empty($tips))
			return WeiFormatter::fromHuman(self::DEFAULT_TIP_GWEI, EtherFormats::GWEI);
		$tmp = new OOGmp(0);
		foreach($tips AS $tip) {
			$tmp = $tmp->add($tip);
		}

		return $tmp->div(count($tips));
	}

	/**
	 * Checks whether the network seems to be like London (EIP-1559) by checking block data fields that are EIP1559 specific
	 *
	 * @return bool true if network seems to be post-London fork, false otherwise
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 */
	public function isLookingLikeLondon(): bool
	{
		if($this->seemsLondon !== null)
			return $this->seemsLondon;
		$this->seemsLondon = $this->ethGetBlockByNumber()->isEIP1559();
		return $this->seemsLondon;
	}
}