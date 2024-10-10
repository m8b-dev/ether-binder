<?php

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * DencunTransaction is transaction type 3, enabled in Proto-DankSharding ethereum upgrade, defined by EIP 4844
 *
 * Note that support is only partial, Ether Binder does not interact with consensus layer at all, so transmitting or
 *  fetching blobs isn't part of binder.
 *
 * @see https://eips.ethereum.org/EIPS/eip-4844
 */
class CancunTransaction extends LondonTransaction
{
	protected OOGmp $maxFeePerBlobGas;
	/** @var Hash[]  */
	protected array $blobVersionedHashes;

	public function __construct()
	{
		$this->maxFeePerBlobGas = new OOGmp();
		parent::__construct();
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	protected function internalEncodeBin(bool $signing, ?int $signingChainID): string
	{
		$nonce            = "0x".dechex($this->nonce);
		$gasFeePrice      = $this->gasPrice->add($this->gasFeeTip)->toString(true);
		$gasLimit         = "0x".dechex($this->gas);
		$to               = $this->to?->toHex();
		$value            = $this->value->toString(true);
		$data             = $this->dataHex();
		$gasFeeTip        = $this->gasFeeTip->toString(true);
		$chainId          = $this->chainId === null ? null : "0x".dechex($this->chainId);
		$accessList       = $this->accessList;
		$blobHashes       = $this->blobVersionedHashes;
		$maxFeePerBlobGas = $this->maxFeePerBlobGas;
		$v                = $this->v()->toString(true);
		$r                = $this->r()->toString(true);
		$s                = $this->s()->toString(true);

		if($signingChainID !== null) {
			$this->chainId = $signingChainID;
			$chainId = $signingChainID;
		}

		if($chainId === null) {
			throw new EthBinderArgumentException("chain ID cannot be null for typed transaction");
		}

		// spec:
		//   [chain_id, nonce, max_priority_fee_per_gas, max_fee_per_gas, gas_limit, to, value, data, access_list,
		//    max_fee_per_blob_gas, blob_versioned_hashes, y_parity, r, s]
		// signing spec:
		//   [chain_id, nonce, max_priority_fee_per_gas, max_fee_per_gas, gas_limit, to, value, data, access_list,
		//     max_fee_per_blob_gas, blob_versioned_hashes]
		$data = [$chainId, $nonce, $gasFeeTip, $gasFeePrice, $gasLimit, $to, $value, $data, $accessList,
			$maxFeePerBlobGas, $blobHashes];
		if(!$signing) {
			$data[] = $v;
			$data[] = $r;
			$data[] = $s;
		}

		return Encoder::encodeBin([TransactionType::BLOB->toTypeByte(),$data]);
	}

	/**
	 *  Returns the transaction type enum.
	 *
	 *  @return TransactionType The transaction type enum value.
	 */
	public function transactionType(): TransactionType
	{
		return TransactionType::BLOB;
	}

	/**
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 */
	protected function blanksFromRPCArr(array $rpcArr): void
	{
		parent::blanksFromRPCArr($rpcArr);
		$this->maxFeePerBlobGas = new OOGmp($rpcArr["maxFeePerBlobGas"]);
		$this->blobVersionedHashes = [];
		foreach($rpcArr["blobVersionedHashes"] ?? [] AS $itm) {
			$this->blobVersionedHashes[] = Hash::fromHex($itm);
		}
	}

	protected function setInnerFromRLPValues(array $rlpValues): void
	{
		list($chainId, $nonce, $maxPriorityFeePerGas, $maxFeePerGas, $gasLimit, $destination, $amount,
			$data, $accessList, $maxFeePerBlobGas, $blobHashes, $v, $r, $s) = $rlpValues;

		$this->setDataHex($data);
		$this->nonce             = hexdec($nonce);
		$this->gasFeeTip         = new OOGmp($maxPriorityFeePerGas);
		$this->gasPrice          = (new OOGmp($maxFeePerGas))->sub($this->gasFeeTip);
		$this->gas               = hexdec(substr($gasLimit, 2));
		$this->chainId           = hexdec(substr($chainId, 2));
		$this->to                = Address::fromHex($destination);
		$this->value             = new OOGmp($amount);
		$this->accessList        = $accessList;
		$this->maxFeePerBlobGas  = new OOGmp($maxFeePerBlobGas);
		$this->v                 = new OOGmp($v);
		$this->r                 = new OOGmp($r);
		$this->s                 = new OOGmp($s);
		$this->signed            = true;

		$this->blobVersionedHashes = [];
		foreach($blobHashes AS $blobHash) {
			if($blobHash instanceof Hash) {
				$this->blobVersionedHashes[] = $blobHash;
			} else {
				$this->blobVersionedHashes[] = Hash::fromHex($blobHash);
			}
		}
	}

	/**
	 * Adds a versioned hash to the blobVersionedHashes array.
	 *
	 * @param Hash $hash The versioned hash to add.
	 */
	public function addVersionedHash(Hash $hash): void
	{
		$this->blobVersionedHashes[] = $hash;
	}

	/**
	 * Returns an array of versioned hashes.
	 *
	 * @return Hash[] Array of versioned hashes.
	 */
	public function versionedHashes(): array
	{
		return $this->blobVersionedHashes;
	}

	/**
	 * Returns the maximum fee per blob gas.
	 *
	 * @return OOGmp The maximum fee per blob gas.
	 */
	public function getMaxFeePerBlobGas(): OOGmp
	{
		return $this->maxFeePerBlobGas;
	}

	/**
	 * Sets the maximum fee per blob gas. Note that this value is not serviced by estimations.
	 *
	 * @param OOGmp $maxFeePerBlobGas The maximum fee per blob gas to set.
	 */
	public function setMaxFeePerBlobGas(OOGmp $maxFeePerBlobGas): void
	{
		$this->maxFeePerBlobGas = $maxFeePerBlobGas;
	}
}
