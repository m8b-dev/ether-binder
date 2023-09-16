<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Misc\EIP1559Config;
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * LondonTransaction handles Ethereum transactions post EIP-1559.
 *
 * @author DubbaThony
 */
class LondonTransaction extends Transaction
{
	protected array $accessList = [];
	protected OOGmp $gasFeeTip;

	public function __construct()
	{
		$this->gasFeeTip = new OOGmp();
		parent::__construct();
	}

	private function internalEncodeBin(bool $signing, ?int $signingChainID): string
	{
		$nonce       = "0x".dechex($this->nonce);
		$gasFeePrice = $this->gasPrice->toString(true);
		$gasLimit    = "0x".dechex($this->gas);
		$to          = $this->to?->toHex();
		$value       = $this->value->toString(true);
		$data        = $this->dataHex();
		$gasFeeTip   = $this->gasFeeTip->toString(true);
		$chainId     = "0x".dechex($this->chainId);
		$accessList  = $this->accessList;
		$v           = $this->v()->toString(true);
		$r           = $this->r()->toString(true);
		$s           = $this->s()->toString(true);

		if($signingChainID !== null) {
			$this->chainId = $signingChainID;
			$chainId = $signingChainID;
		}
		/*
		 * return prefixedRlpHash(
		tx.Type(),
		[]interface{}{
			s.chainId,
			tx.Nonce(),
			tx.GasTipCap(),
			tx.GasFeeCap(),
			tx.Gas(),
			tx.To(),
			tx.Value(),
			tx.Data(),
			tx.AccessList(),
		})*/
		if($signing)
			return Encoder::encodeBin([TransactionType::DYNAMIC_FEE->toTypeByte(), [
				$chainId, $nonce, $gasFeeTip, $gasFeePrice, $gasLimit, $to, $value, $data, $accessList
			]]);
		return Encoder::encodeBin([TransactionType::DYNAMIC_FEE->toTypeByte(), [
			$chainId, $nonce, $gasFeeTip, $gasFeePrice, $gasLimit, $to, $value, $data, $accessList, $v, $r, $s
		]]);
	}

	/**
	 *  Encodes the transaction for signing with optional chain ID (which differs from encoding for storage
	 *  or transfer. Difference is for example missing fields).
	 *
	 *  @param int|null $chainId The chain ID to use for signing. If null, the transaction's current chain ID will be used.
	 *  @return string The encoded transaction.
	 */
	public function encodeBinForSigning(?int $chainId): string
	{
		return $this->internalEncodeBin(true, $chainId);
	}

	/**
	 *  RLP-encodes the transaction into binary format.
	 *
	 *  @return string The encoded transaction.
	 */
	public function encodeBin(): string
	{
		return $this->internalEncodeBin(false, null);
	}

	/**
	 *  Returns the transaction type enum.
	 *
	 *  @return TransactionType The transaction type enum value.
	 */
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

	/**
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexLengthException
	 * @throws InvalidHexException
	 * @throws HexBlobNotEvenException
	 */
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

	/**
	 *  Calculates the total gas price for the transaction of both tip and base fee.
	 *
	 *  @return OOGmp The total gas price.
	 */
	public function totalGasPrice(): OOGmp
	{
		return $this->gasPrice->add($this->gasFeeTip);
	}

	/**
	 *  Recovers the address of the signer from the signature. Returns null address if is not signed.
	 *
	 *  @return Address The address of the signer.
	 *  @throws EthBinderRuntimeException
	 *  @throws EthBinderLogicException
	 *  @throws InvalidLengthException
	 */
	public function ecRecover(): Address
	{
		if(!$this->isSigned())
			return Address::NULL();
		$hash = $this->getSigningHash($this->chainId);
		return EC::Recover($hash, $this->r, $this->s, $this->v->mod(2));
	}

	/**
	 *  Returns the transaction's access list.
	 *
	 *  @return array The access list.
	 */
	public function accessList(): array
	{
		return $this->accessList;
	}

	/**
	 *  Sets the access list for the transaction.
	 *   This invalidates signature if data differs from existing data.
	 *
	 *  @param array $accessList The new access list.
	 *  @return static
	 */
	public function setAccessList(array $accessList): static
	{
		if($this->accessList == $accessList) {
			$this->signed = false;
			$this->accessList = $accessList;
		}
		return $this;
	}

	/**
	 *  Returns the chain ID for the transaction.
	 *
	 *  @return int The chain ID.
	 */
	public function chainId(): int
	{
		return $this->chainId;
	}

	/**
	 *  Sets the chain ID for the transaction.
	 *   This invalidates signature if data differs from existing data.
	 *
	 *  @param int $chainId The new chain ID.
	 *  @return static
	 */
	public function setChainId(int $chainId): static
	{
		if($this->chainId !== $chainId) {
			$this->signed = false;
			$this->chainId = $chainId;
		}
		return $this;
	}

	/**
	 *  Returns the base fee cap.
	 *
	 *  @return OOGmp The base fee cap.
	 */
	public function getBaseFeeCap(): OOGmp
	{
		return $this->gasPrice;
	}

	/**
	 * Sets the base fee cap for the transaction.
	 *   This invalidates signature if data differs from existing data.
	 *
	 * @param OOGmp $fee The new base fee cap.
	 * @return static
	 */
	public function setBaseFeeCap(OOGmp $fee): static
	{
		return parent::setGasPriceOrBaseFee($fee);
	}

	/**
	 *  Gets the gas fee tip.
	 *
	 *  @return OOGmp The gas fee tip.
	 */
	public function getGasFeeTip(): OOGmp
	{
		return $this->gasFeeTip;
	}

	/**
	 * Sets the gas fee tip for the transaction.
	 *   This invalidates signature if data differs from existing data.
	 *
	 * @param OOGmp $gasFeeTip The new gas fee tip.
	 * @return static
	 */
	public function setGasFeeTip(OOGmp $gasFeeTip): static
	{
		if(!$gasFeeTip->eq($this->gasFeeTip)) {
			$this->signed = false;
			$this->gasFeeTip = $gasFeeTip;
		}
		return $this;
	}

	/**
	 * Updates the transaction gas and fee estimates using RPC, with added N percent "bump". Note that percentage is
	 * added, so if 120% of minimal value is required, param should be 20, not 120.
	 *   This invalidates signature if data differs from existing data.
	 *
	 *  @param AbstractRPC $rpc The RPC client.
	 *  @param Address|null $from The sender's address.
	 *  @param int $bumpGasPercentage Increase in gas limit as a percentage.
	 *  @param int $bumpFeePercentage Increase in fee as a percentage.
	 *  @return static
	 *  @throws EthBinderLogicException
	 *  @throws RPCInvalidResponseParamException
	 */
	public function useRpcEstimatesWithBump(AbstractRPC $rpc, ?Address $from, int $bumpGasPercentage, int $bumpFeePercentage): static
	{
		$gas   = ($rpc->ethEstimateGas($this, $from) * ($bumpFeePercentage + 100)) / 100;
		$base  = Functions::getNextBlockBaseFee($rpc->ethGetBlockByNumber(), EIP1559Config::sepolia() /* using sepolia as only difference
 				 for this config is start block, which is 0. This function is expected to be called on London transaction
                 for London-enabled chains, regardless of starting block */)
			->mul($bumpFeePercentage + 100)->div(100);
		$tip = $rpc->calcAvgTip()->mul($bumpFeePercentage + 100)->div(100);

		$this->setGasLimit($gas);
		$this->setBaseFeeCap($base);
		return $this->setGasFeeTip($tip);
	}

	/**
	 * in london transactions, the chainID is part of transaction data, and V is "vanilla" ECDSA signature recovery
	 * param, without any alteration. See https://eips.ethereum.org/EIPS/eip-2930
	 * @inheritDoc
	 */
	public function calculateV(OOGmp $recovery): OOGmp
	{
		return $recovery->mod(2);
	}
}
