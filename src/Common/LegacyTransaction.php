<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;

class LegacyTransaction extends Transaction
{
	private function internalEncodeBin(bool $signing, ?int $signingChainID): string
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

		// this method will be used for ECRecover, which may also happen to use non-replay protected transactions, ie.
		//  for traversing historic transactions pre-EIP-155.
		if($signingChainID !== null)
			return Encoder::encodeBin([[$nonce, $gasPrice, $gasLimit, $to, $value, $data, $signingChainID, 0, 0]]);
		if($signing)
			return Encoder::encodeBin([[$nonce, $gasPrice, $gasLimit, $to, $value, $data]]);
		return Encoder::encodeBin([[$nonce, $gasPrice, $gasLimit, $to, $value, $data, $v, $r, $s]]);
	}

	public function encodeBinForSigning(?int $chainId): string
	{
		return $this->internalEncodeBin(true, $chainId);
	}

	public function encodeBin(): string
	{
		return $this->internalEncodeBin(false, null);
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

	public function ecRecover(): Address
	{
		if(!$this->isSigned())
			return Address::NULL();
		if($this->isReplayProtected()) {
			$v = $this->v->toInt();
			// v is {0,1} + CHAIN_ID * 2 + 35
			$v -= 35;
			$chainId = ($v - $v % 2)/2; //160038 for mumbai for example
		} else {
			$chainId = null;
		}
		$hash = $this->getSigningHash($chainId);
		// this is weird - specs subtract 27 or 35, keeping it that way, that it requires getting "flipped"
		$parity = new OOGmp($this->v->mod(2)->toInt() == 0 ? 1 : 0);
		return EC::Recover($hash, $this->r, $this->s, $parity);
	}

	//todo:
	// if supporting signing new antique transactions would be required, add calculateV override

	public function isReplayProtected(): bool
	{
		return !($this->v->eq(27) || $this->v->eq(28) || $this->v->eq(0) || $this->v->eq(1));
	}

	public function setGasPrice(OOGmp $gasPrice): static
	{
		return parent::setGasPriceOrBaseFee($gasPrice);
	}

	public function useRpcEstimatesWithBump(AbstractRPC $rpc, ?Address $from, int $bumpGasPercentage, int $bumpFeePercentage)
	{
		$gas      = ($rpc->ethEstimateGas($this, $from) * ($bumpFeePercentage + 100)) / 100;
		$gasPrice = $rpc->ethGasPrice()->mul($bumpFeePercentage + 100)->div(100);
		$this->setGasLimit($gas);
		$this->setGasPrice($gasPrice);
	}

}
