<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Utils\OOGmp;

abstract class Transaction
{
	protected bool $signed = false;

	protected int $nonce;
	protected int $gas;
	protected OOGmp $gasPriceOrBaseFee;
	protected ?OOGmp $gasFeeTipCap = null;
	protected OOGmp $value;
	protected ?Address $to = null;
	protected string $dataBin;
	protected OOGmp $v;
	protected OOGmp $r;
	protected OOGmp $s;

	public function __construct()
	{
		$this->gasPriceOrBaseFee = new OOGmp();
		$this->value = new OOGmp();
		$this->v = new OOGmp();
		$this->r = new OOGmp();
		$this->s = new OOGmp();
	}

	// fixme: in hex, we should decide type from hex value, not rely on caller to know it for us.
	abstract public static function decodeHex(string $rlp): static;
	abstract public static function decodeBin(string $rlp): static;

	abstract public function encodeHex(): string;
	abstract public function encodeBin(): string;
	abstract public function transactionType(): TransactionType;
	abstract protected function blanksFromRPCArr(array $rpcArr):void;

	public static function fromRPCArr(array $rpcArr): static
	{
		$static = TransactionType::numericToEnum($rpcArr["type"] ?? 0)->spawnSuchTransaction();
		$static->nonce             = hexdec($rpcArr["nonce"]);
		$static->gas               = hexdec($rpcArr["gas"]);
		$static->gasPriceOrBaseFee = new OOGmp($rpcArr["gasPrice"]);
		$static->value             = new OOGmp($rpcArr["value"]);
		$static->to                = Address::fromHex($rpcArr["to"]);

		if(!empty($rpcArr["data"]))
			$static->setDataBin($rpcArr["data"]);

		$static->blanksFromRPCArr($rpcArr);

		if(!empty($rpcArr["r"]) && !empty($rpcArr["s"])) {
			$static->v      = new OOGmp($rpcArr["v"]);
			$static->r      = new OOGmp($rpcArr["r"]);
			$static->s      = new OOGmp($rpcArr["s"]);
			$static->signed = true;
		}
		return $static;
	}

	public function setNonce(int $nonce): static
	{
		if($this->nonce != $nonce) {
			$this->nonce = $nonce;
			$this->signed = false;
		}
		return $this;
	}

	public function nonce(): int
	{
		return $this->nonce;
	}

	public function setGasLimit(int $gasLimit): static
	{
		if($this->gas != $gasLimit) {
			$this->gas = $gasLimit;
			$this->signed = false;
		}
		return $this;
	}

	public function gasLimit(): int
	{
		return $this->gas;
	}

	// to be used by inherited class with correct name: for post-london transactions base fee, or for pre-london / legacy gas price
	protected function setGasPriceOrBaseFee(OOGmp $fee): self
	{
		if($fee->raw() != $this->gasPriceOrBaseFee->raw()) {
			$this->gasPriceOrBaseFee = $fee;
			$this->signed = false;
		}
		return $this;
	}

	protected function gasPriceOrBaseFee(): OOGmp
	{
		return $this->gasPriceOrBaseFee;
	}

	public function totalGasPrice(): OOGmp
	{
		$a = $this->gasPriceOrBaseFee->raw();
		$b = $this->gasFeeTipCap === null ? gmp_init(0) : $this->gasFeeTipCap->raw();
		return $a + $b;
	}

	public function setValue(OOGmp $valueWEI): self
	{
		if($this->value != $valueWEI) {
			$this->value = $valueWEI;
			$this->signed = false;
		}
		return $this;
	}

	public function value(): OOGmp
	{
		return $this->value;
	}

	public function setTo(?Address $address): static
	{
		if(
			   ($this->to === null && $address !== null)
			|| ($this->to !== null && $address === null)
			|| (!$this->to->eq($address))
		) {
			$this->to = $address;
			$this->signed = false;
		}
		return $this;
	}

	public function to(): ?Address
	{
		return $this->to;
	}

	public function setDataBin(string $dataBin): static
	{
		if($dataBin !== $this->dataBin) {
			$this->dataBin = $dataBin;
			$this->signed = false;
		}
		return $this;
	}

	public function setDataHex(string $dataHex): static
	{
		if(str_starts_with($dataHex, "0x"))
			$dataHex = substr($dataHex, 2);
		if(strlen($dataHex) % 2 !== 0)
			throw new HexBlobNotEvenException();
		return $this->setDataBin(hex2bin($dataHex));
	}

	public function dataHex(): string
	{
		if(empty($this->dataBin))
			return "0x";
		return "0x".bin2hex($this->dataBin);
	}

	public function dataBin(): string
	{
		return $this->dataBin;
	}

	public function v(): OOGmp
	{
		return $this->v;
	}

	public function r(): OOGmp
	{
		return $this->r;
	}

	public function s(): OOGmp
	{
		return $this->s;
	}

	public function isSigned(): bool
	{
		return $this->signed;
	}
}