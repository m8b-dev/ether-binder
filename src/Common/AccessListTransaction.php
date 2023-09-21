<?php

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

class AccessListTransaction extends LegacyTransaction
{
	protected array $accessList = [];

	/**
	 * @throws EthBinderArgumentException
	 */
	private function internalEncodeBin(bool $signing, ?int $signingChainID): string
	{
		$nonce      = "0x".dechex($this->nonce);
		$gasPrice   = $this->gasPrice->toString(true);
		$gasLimit   = "0x".dechex($this->gas);
		$to         = $this->to?->toHex();
		$value      = $this->value->toString(true);
		$data       = $this->dataHex();
		$accessList = $this->accessList;
		$chainId    = $this->chainId;
		$v          = $this->v()->toString(true);
		$r          = $this->r()->toString(true);
		$s          = $this->s()->toString(true);

		if($signingChainID !== null) {
			$this->chainId = $signingChainID;
			$chainId = $signingChainID;
		}

		if($chainId === null) {
			throw new EthBinderArgumentException("chain ID cannot be null for typed transaction");
		}

		if($signing)
			return Encoder::encodeBin([TransactionType::ACCESS_LIST->toTypeByte(), [
				$chainId, $nonce, $gasPrice, $gasLimit, $to, $value, $data, $accessList]]);

		return Encoder::encodeBin([TransactionType::ACCESS_LIST->toTypeByte(), [
			$chainId, $nonce, $gasPrice, $gasLimit, $to, $value, $data, $accessList, $v, $r, $s]]);
	}


	/**
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexLengthException
	 * @throws InvalidHexException
	 * @throws EthBinderRuntimeException
	 * @throws HexBlobNotEvenException
	 */
	protected function setInnerFromRLPValues(array $rlpValues): void
	{
		list($chainId, $nonce, $gasPrice, $gasLimit, $to, $value, $data, $accessList, $v, $r, $s) = $rlpValues;
		$this->setDataHex($data);

		$this->nonce             = hexdec($nonce);
		$this->gasPrice          = new OOGmp($gasPrice);
		$this->gas               = hexdec($gasLimit);
		$this->chainId           = Functions::hex2int($chainId);
		$this->accessList        = $accessList;
		$this->to                = Address::fromHex($to);
		$this->value             = new OOGmp($value);
		$this->v                 = new OOGmp($v);
		$this->r                 = new OOGmp($r);
		$this->s                 = new OOGmp($s);
		$this->signed            = true;
	}

	protected function blanksFromRPCArr(array $rpcArr): void
	{
		parent::blanksFromRPCArr($rpcArr);
		$this->accessList = $rpcArr["accessList"];
	}

	/**
	 * @inheritDoc
	 */
	public function calculateV(OOGmp $recovery): OOGmp
	{
		return $recovery->mod(2);
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
	 * @inheritDoc
	 * @throws EthBinderArgumentException
	 */
	public function encodeBin(): string
	{
		return $this->internalEncodeBin(false, null);
	}


	/**
	 * Encodes the transaction for signing purposes (which differs from encoding for storage
	 *   or transfer. Difference is for example missing fields).
	 *
	 * @param  int|null $chainId The chain ID of the Ethereum network.
	 * @return string Binary representation of the transaction for signing.
	 * @throws EthBinderArgumentException
	 */
	public function encodeBinForSigning(?int $chainId): string
	{
		return $this->internalEncodeBin(true, $chainId);
	}
}
