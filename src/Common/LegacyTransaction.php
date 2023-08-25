<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\Utils\OOGmp;

class LegacyTransaction extends Transaction
{
	public function encodeBin(): string
	{
		$nonce    = "0x".dechex($this->nonce);
		$gasPrice = $this->gasPrice->toString(true);
		$gasLimit = "0x".dechex($this->gas);
		$to       = $this->to->toHex();
		$value    = $this->value->toString(true);
		$data     = $this->dataHex();
		$v        = $this->v()->toString(true);
		$r        = $this->r()->toString(true);
		$s        = $this->s()->toString(true);
		return Encoder::encodeBin([[$nonce, $gasPrice, $gasLimit, $to, $value, $data, $v, $r, $s]]);
	}

	public function transactionType(): TransactionType
	{
		return TransactionType::LEGACY;
	}

	protected function blanksFromRPCArr(array $rpcArr): void
	{}

	protected function setInnerFromRLPValues(array $rlpValues): void
	{
		list($nonce, $gasPrice, $gasLimit, $to, $value, $data, $v, $r, $s) = $rlpValues;
		$this->setDataHex($data);

		$this->nonce             = hexdec($nonce);
		$this->gasPrice          = new OOGmp($gasPrice);
		$this->gas               = hexdec($gasLimit);
		$this->to                = Address::fromHex($to);
		$this->value             = new OOGmp($value);
		$this->v                 = new OOGmp($v);
		$this->r                 = new OOGmp($r);
		$this->s                 = new OOGmp($s);
		$this->signed            = true;
	}
}
