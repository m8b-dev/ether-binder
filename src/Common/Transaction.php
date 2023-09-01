<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use kornrunner\Keccak;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\RLP\Decoder;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\EtherFormats;
use M8B\EtherBinder\Utils\OOGmp;
use M8B\EtherBinder\Utils\WeiFormatter;

abstract class Transaction
{
	protected bool $signed = false;

	protected int $nonce = 0;
	protected int $gas   = 0;
	protected OOGmp $gasPrice;
	protected OOGmp $value;
	protected ?Address $to    = null;
	protected string $dataBin = "";
	protected OOGmp $v;
	protected OOGmp $r;
	protected OOGmp $s;
	protected ?int $chainId = null;

	public function __construct()
	{
		$this->gasPrice = new OOGmp();
		$this->value = new OOGmp();
		$this->v = new OOGmp();
		$this->r = new OOGmp();
		$this->s = new OOGmp();
	}

	// fixme: in hex, we should decide type from hex value, not rely on caller to know it for us.
	public static function decodeHex(string $rlp): static
	{
		if(str_starts_with($rlp, "0x"))
			$rlp = substr($rlp, 2);
		return static::decodeBin(hex2bin($rlp));
	}

	public static function decodeBin(string $rlp): static
	{
		$txDataRaw = Decoder::decodeRLPBin($rlp);
		if(is_array($txDataRaw[0])) {
			// untyped transaction, is legacy.
			$txData = $txDataRaw[0];
			$type = TransactionType::LEGACY;
		} else {
			// 0xTYPE || [transaction rlp] for typed transaction envelope
			$txData = $txDataRaw[1];
			$type = TransactionType::numericToEnum($rlp[0]);
		}

		$tx = $type->spawnSuchTransaction();
		$tx->setInnerFromRLPValues($txData);
		return $tx;
	}

	public function encodeHexForSigning(?int $chainId): string
	{
		return "0x".bin2hex($this->encodeBinForSigning($chainId));
	}

	public function encodeHex(): string
	{
		return "0x".bin2hex($this->encodeBin());
	}

	abstract public function encodeBin(): string;
	abstract public function encodeBinForSigning(?int $chainId): string;
	abstract public function transactionType(): TransactionType;
	abstract protected function blanksFromRPCArr(array $rpcArr): void;
	abstract protected function setInnerFromRLPValues(array $rlpValues): void;
	abstract public function ecRecover(): Address;
	abstract public function useRpcEstimatesWithBump(AbstractRPC $rpc, ?Address $from, int $bumpGasPercentage, int $bumpFeePercentage);

	public static function fromRPCArr(array $rpcArr): static
	{
		$static = TransactionType::numericToEnum($rpcArr["type"] ?? 0)->spawnSuchTransaction();
		$static->nonce             = hexdec($rpcArr["nonce"]);
		$static->gas               = hexdec($rpcArr["gas"]);
		$static->gasPrice = new OOGmp($rpcArr["gasPrice"]);
		$static->value             = new OOGmp($rpcArr["value"]);
		$static->to                = $rpcArr["to"] === null ? null : Address::fromHex($rpcArr["to"]);

		if(!empty($rpcArr["data"]))
			$static->setDataHex($rpcArr["data"]);
		elseif(!empty($rpcArr["input"]))
			$static->setDataHex($rpcArr["input"]);

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
	protected function setGasPriceOrBaseFee(OOGmp $fee): static
	{
		if(!$fee->eq($this->gasPrice)) {
			$this->gasPrice = $fee;
			$this->signed = false;
		}
		return $this;
	}

	public function totalGasPrice(): OOGmp
	{
		return $this->gasPrice;
	}

	public function setValueFmt(float|int|string|OOGmp $human, int|string|EtherFormats $format = EtherFormats::ETHER): static
	{
		return $this->setValue(WeiFormatter::toWei($human, $format));
	}

	public function setValue(OOGmp $valueWEI): static
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

	public function valueFmt(int $finalDecimals, int|string|EtherFormats $format = EtherFormats::ETHER): string
	{
		return WeiFormatter::fromWei($this->value, $finalDecimals, $format);
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

	public function hash(): Hash
	{
		return Hash::fromBin(Keccak::hash($this->encodeBin(), 256, true));
	}

	public function getSigningHash(?int $chainId): Hash
	{
		return Hash::fromBin(Keccak::hash($this->encodeBinForSigning($chainId), 256, true));
	}

	public function sign(Key $key, ?int $chainId): void
	{
		$this->chainId = $chainId;
		$sig = $key->sign($this->getSigningHash($chainId));
		$this->r = $sig->r;
		$this->s = $sig->s;
		$this->v = $this->calculateV($sig->v);
		$this->signed = true;
	}

	public function calculateV(OOGmp $recovery): OOGmp
	{
		if($this->chainId === null)
			return $recovery->add(27);
		return $recovery->add($this->chainId * 2)->add(35);
	}

	public function useRpcEstimates(AbstractRPC $rpc, Address $from)
	{
		return $this->useRpcEstimatesWithBump($rpc, $from, 0, 0);
	}
}