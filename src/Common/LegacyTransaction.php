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
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * LegacyTransaction is a class for handling Ethereum legacy transactions (pre EIP1559), with pre EIP155 or post EIP155
 * support
 *
 * @author DubbaThony
 */
class LegacyTransaction extends Transaction
{
	private function internalEncodeBin(bool $signing, ?int $signingChainID): string
	{
		$nonce    = "0x".dechex($this->nonce);
		$gasPrice = $this->gasPrice->toString(true);
		$gasLimit = "0x".dechex($this->gas);
		$to       = $this->to?->toHex();
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

	/**
	 * Encodes the transaction for signing purposes (which differs from encoding for storage
	 *   or transfer. Difference is for example missing fields).
	 *
	 * @param int|null $chainId The chain ID of the Ethereum network.
	 * @return string Binary representation of the transaction for signing.
	 */
	public function encodeBinForSigning(?int $chainId): string
	{
		return $this->internalEncodeBin(true, $chainId);
	}

	/**
	 * Encodes the transaction into a binary blob.
	 *
	 * @return string Binary blob of the transaction.
	 */
	public function encodeBin(): string
	{
		return $this->internalEncodeBin(false, null);
	}

	/**
	 * Returns the type of the transaction, which is always LEGACY.
	 *
	 * @return TransactionType Returns LEGACY as the transaction type.
	 */
	public function transactionType(): TransactionType
	{
		return TransactionType::LEGACY;
	}


	protected function blanksFromRPCArr(array $rpcArr): void
	{}


	/**
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexLengthException
	 * @throws InvalidHexException
	 * @throws HexBlobNotEvenException
	 */
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

	/**
	 * Recovers the sender address from the transaction signature.
	 *
	 * @throws EthBinderRuntimeException
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @return Address The address of the transaction sender.
	 */
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

	/**
	 * Checks if the transaction is replay-protected (post EIP155).
	 *
	 * @return bool True if the transaction is replay-protected, false otherwise.
	 */
	public function isReplayProtected(): bool
	{
		return !($this->v->eq(27) || $this->v->eq(28) || $this->v->eq(0) || $this->v->eq(1));
	}

	/**
	 * Sets the gas price for the transaction.
	 *
	 * @param OOGmp $gasPrice The new gas price.
	 * @return static Returns the instance of the class.
	 */
	public function setGasPrice(OOGmp $gasPrice): static
	{
		return parent::setGasPriceOrBaseFee($gasPrice);
	}

	/**
	 * Uses RPC to estimate gas and fee, then sets them with an optional bump.
	 *
	 * @param AbstractRPC $rpc The RPC client.
	 * @param Address|null $from The sender's address.
	 * @param int $bumpGasPercentage Percentage to bump the estimated gas.
	 * @param int $bumpFeePercentage Percentage to bump the estimated fee.
	 * @return static Returns the instance of the class.
	 */
	public function useRpcEstimatesWithBump(AbstractRPC $rpc, ?Address $from, int $bumpGasPercentage, int $bumpFeePercentage): static
	{
		$gas      = ($rpc->ethEstimateGas($this, $from) * ($bumpFeePercentage + 100)) / 100;
		$gasPrice = $rpc->ethGasPrice()->mul($bumpFeePercentage + 100)->div(100);
		$this->setGasLimit($gas);
		return $this->setGasPrice($gasPrice);
	}

}
