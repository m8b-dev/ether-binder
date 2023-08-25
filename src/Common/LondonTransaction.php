<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\Utils\OOGmp;

class LondonTransaction extends Transaction
{
	protected array $accessList;
	protected int $chainId;
	protected OOGmp $gasFeeTip;

	public function __construct()
	{
		$this->gasFeeTip = new OOGmp();
		parent::__construct();
	}

	public function encodeBin(): string
	{
		$nonce      = "0x".dechex($this->nonce);
		$gasPrice   = $this->gasPrice->toString(true);
		$gasLimit   = "0x".dechex($this->gas);
		$to         = $this->to->toHex();
		$value      = $this->value->toString(true);
		$data       = $this->dataHex();
		$gasFeeTip  = $this->gasFeeTip->toString(true);
		$chainId    = "0x".dechex($this->chainId);
		$accessList = $this->accessList;
		$v          = $this->v()->toString(true);
		$r          = $this->r()->toString(true);
		$s          = $this->s()->toString(true);

		return Encoder::encodeBin([TransactionType::DYNAMIC_FEE->toTypeByte(), [
			$chainId, $nonce, $gasFeeTip, $gasPrice, $gasLimit, $to, $value, $data, $accessList, $v, $r, $s
		]]);
	}

	public function transactionType(): TransactionType
	{
		return TransactionType::DYNAMIC_FEE;
	}

	protected function blanksFromRPCArr(array $rpcArr): void
	{
		$this->gasFeeTip  = new OOGmp($rpcArr["maxPriorityFeePerGas"]);
		$this->chainId    = hexdec(substr($rpcArr["chainId"], 2));
		$this->accessList = $rpcArr["accessList"];
	}

	protected function setInnerFromRLPValues(array $rlpValues): void
	{
		list($chainId, $nonce, $maxPriorityFeePerGas, $maxFeePerGas, $gasLimit, $destination, $amount,
			$data, $accessList, $v, $r, $s) = $rlpValues;

		$this->setDataHex($data);
		$this->nonce             = hexdec($nonce);
		$this->gasPrice          = new OOGmp($maxFeePerGas);
		$this->gasFeeTip         = new OOGmp($maxPriorityFeePerGas);
		$this->gas               = hexdec(substr($gasLimit, 2));
		$this->chainId           = hexdec(substr($chainId, 2));
		$this->to                = Address::fromHex($destination);
		$this->value             = new OOGmp($amount);
		$this->accessList        = $accessList;
		$this->v                 = new OOGmp($v);
		$this->r                 = new OOGmp($r);
		$this->s                 = new OOGmp($s);
		$this->signed            = true;
	}

	public function totalGasPrice(): OOGmp
	{
		return $this->gasPrice->add($this->gasFeeTip);
	}
}
