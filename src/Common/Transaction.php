<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Crypto\Signature;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\RLP\Decoder;
use M8B\EtherBinder\RLP\Encoder;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\EtherFormats;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;
use M8B\EtherBinder\Utils\WeiFormatter;

/**
 * Transaction serves as an abstract base class for Ethereum transactions. It represents any transaction, can be signed
 * or unsigned.
 *
 * @author DubbaThony
 */
abstract class Transaction implements BinarySerializableInterface
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

	/**
	 * @throws EthBinderArgumentException
	 */
	abstract public    function encodeBin(): string;

	/**
	 * @throws EthBinderArgumentException
	 */
	abstract public    function encodeBinForSigning(?int $chainId): string;
	abstract public    function transactionType(): TransactionType;

	/**
	 * @throws EthBinderRuntimeException
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 */
	abstract public    function ecRecover(): Address;
	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	abstract public    function useRpcEstimatesWithBump(
		AbstractRPC $rpc, ?Address $from, int $bumpGasPercentage, int $bumpFeePercentage): static;
	abstract protected function blanksFromRPCArr(array $rpcArr): void;
	/**
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexLengthException
	 * @throws InvalidHexException
	 * @throws EthBinderRuntimeException
	 */
	abstract protected function setInnerFromRLPValues(array $rlpValues): void;


	public function __construct()
	{
		$this->gasPrice = new OOGmp();
		$this->value = new OOGmp();
		$this->v = new OOGmp();
		$this->r = new OOGmp();
		$this->s = new OOGmp();
	}

	/**
	 * Decodes a hexadecimal RLP-encoded transaction. Accepts both legacy formatting and typed transaction.
	 *
	 * @param string $rlp The RLP-encoded transaction as a hexadecimal string.
	 * @return static The decoded Transaction object.
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws NotSupportedException
	 */
	public static function decodeHex(string $rlp): static
	{
		return static::decodeBin(Functions::hex2bin($rlp));
	}

	/**
	 * Decodes a binary RLP-encoded transaction. Accepts both legacy formatting and typed transaction.
	 *
	 * @param string $rlp The RLP-encoded transaction as a binary string.
	 * @return static The decoded Transaction object.
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws NotSupportedException
	 */
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

	/**
	 * Alias function for decodeHex()
	 *
	 * @see static::decodeHex()
	 * @param string $hex
	 * @return static
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws NotSupportedException
	 */
	public static function fromHex(string $hex): static
	{ return static::decodeHex($hex); }

	/**
	 * Alias function for decodeBin()
	 *
	 * @see static::decodeBin()
	 * @param string $bin
	 * @return static
	 * @throws BadAddressChecksumException
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 * @throws NotSupportedException
	 */
	public static function fromBin(string $bin): static
	{ return static::decodeBin($bin); }

	/**
	 * Alias function for encodeBin()
	 *
	 * @return string
	 * @throws EthBinderArgumentException
	 * @see static::encodeBin()
	 */
	public function toBin(): string
	{ return $this->encodeBin(); }

	/**
	 * Alias function for encodeHex()
	 *
	 * @see static::encodeHex()
	 * @return string
	 * @throws EthBinderArgumentException
	 */
	public function toHex(): string
	{ return $this->encodeHex(); }

	/**
	 * Encodes the transaction into a hexadecimal string for signing purposes (which differs from encoding for storage
	 * or transfer. Difference is for example missing fields).
	 *
	 * @param ?int $chainId The chain ID for the transaction.
	 * @return string The hexadecimal encoded transaction.
	 * @throws EthBinderArgumentException
	 */
	public function encodeHexForSigning(?int $chainId): string
	{
		return "0x".bin2hex($this->encodeBinForSigning($chainId));
	}

	/**
	 * Encodes the transaction into a hexadecimal string.
	 *
	 * @return string The hexadecimal encoded transaction.
	 * @throws EthBinderArgumentException
	 */
	public function encodeHex(): string
	{
		return "0x".bin2hex($this->encodeBin());
	}

	/**
	 * Creates a transaction from an RPC array.
	 *
	 * @param array $rpcArr The array containing transaction details from RPC.
	 * @return static The created Transaction object.
	 * @throws BadAddressChecksumException
	 * @throws NotSupportedException
	 * @throws InvalidHexLengthException
	 * @throws EthBinderLogicException
	 * @throws InvalidHexException
	 * @throws HexBlobNotEvenException
	 */
	public static function fromRPCArr(array $rpcArr): static
	{
		$static           = TransactionType::numericToEnum($rpcArr["type"] ?? 0)->spawnSuchTransaction();
		$static->nonce    = hexdec($rpcArr["nonce"]);
		$static->gas      = hexdec($rpcArr["gas"]);
		$static->gasPrice = new OOGmp($rpcArr["gasPrice"]);
		$static->value    = new OOGmp($rpcArr["value"]);
		$static->to       = $rpcArr["to"] === null ? null : Address::fromHex($rpcArr["to"]);

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

	/**
	 * Sets the nonce for the transaction.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param int $nonce The nonce value.
	 * @return static The updated Transaction object.
	 */
	public function setNonce(int $nonce): static
	{
		if($this->nonce != $nonce) {
			$this->nonce = $nonce;
			$this->signed = false;
		}
		return $this;
	}

	/**
	 * Gets the nonce of the transaction.
	 *
	 * @return int The nonce value.
	 */
	public function nonce(): int
	{
		return $this->nonce;
	}

	/**
	 * Sets the gas limit for the transaction.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param int $gasLimit The gas limit value.
	 * @return static The updated Transaction object.
	 */
	public function setGasLimit(int $gasLimit): static
	{
		if($this->gas != $gasLimit) {
			$this->gas = $gasLimit;
			$this->signed = false;
		}
		return $this;
	}

	/**
	 * Gets the gas limit of the transaction.
	 *
	 * @return int The gas limit value.
	 */
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

	/**
	 * Gets the total gas price for the transaction.
	 *
	 * @return OOGmp Total gas price.
	 */
	public function totalGasPrice(): OOGmp
	{
		return $this->gasPrice;
	}

	/**
	 * Sets the value for the transaction using WeiFormatter. Accepts "human" input.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param float|int|string|OOGmp $human
	 * @param int|string|EtherFormats $format
	 * @return static The updated Transaction object.
	 */
	public function setValueFmt(float|int|string|OOGmp $human, int|string|EtherFormats $format = EtherFormats::ETHER): static
	{
		return $this->setValue(WeiFormatter::fromHuman($human, $format));
	}

	/**
	 * Sets the value for the transaction.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param OOGmp $valueWEI The value in Wei.
	 * @return static The updated Transaction object.
	 */
	public function setValue(OOGmp $valueWEI): static
	{
		if(!$this->value->eq($valueWEI)) {
			$this->value = $valueWEI;
			$this->signed = false;
		}
		return $this;
	}

	/**
	 * Gets the value of the transaction.
	 *
	 * @return OOGmp The value in Wei.
	 */
	public function value(): OOGmp
	{
		return $this->value;
	}

	/**
	 * Gets the value of the transaction and formats it with WeiFormatter, proxying params to it.
	 *
	 * @param int $finalDecimals Number of decimals for formatting.
	 * @param int|string|EtherFormats $format Ether format.
	 * @return string Formatted value.
	 */
	public function valueFmt(int $finalDecimals, int|string|EtherFormats $format = EtherFormats::ETHER): string
	{
		return WeiFormatter::fromWei($this->value, $finalDecimals, $format);
	}

	/**
	 * Sets the recipient address for the transaction. If it's null, the transaction is contract deploy transaction.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param Address|null $address The Address object or null. If null, the transaction is contract deploy
	 * @return static The updated Transaction object.
	 */
	public function setTo(?Address $address): static
	{
		if(
			   $this->to === null && $address === null
			|| ($this->to !== null && $address !== null && $this->to->eq($address))
		) return $this;

		$this->to = $address;
		$this->signed = false;

		return $this;
	}

	/**
	 * Gets the recipient address of the transaction, null if it's deploy transaction.
	 *
	 * @return Address|null The recipient address or null (deploy).
	 */
	public function to(): ?Address
	{
		return $this->to;
	}

	/**
	 * Sets the data payload for the transaction using binary blob.
	 *  This invalidates signature if data differs from existing data.
	 *
	 * @param string $dataBin The binary data.
	 * @return static The updated Transaction object.
	 */
	public function setDataBin(string $dataBin): static
	{
		if($dataBin !== $this->dataBin) {
			$this->dataBin = $dataBin;
			$this->signed = false;
		}
		return $this;
	}

	/**
	 * Sets the data for the transaction using a hex string.
	 *   This invalidates signature if data differs from existing data.
	 *
	 * @param string $dataHex Data in hex format.
	 * @return static
	 * @throws HexBlobNotEvenException
	 * @throws InvalidHexException
	 */
	public function setDataHex(string $dataHex): static
	{
		if(str_starts_with($dataHex, "0x"))
			$dataHex = substr($dataHex, 2);
		if(strlen($dataHex) % 2 !== 0)
			throw new HexBlobNotEvenException();
		return $this->setDataBin(Functions::hex2bin($dataHex));
	}

	/**
	 * Gets the transaction data in hex format.
	 *
	 * @return string Data in hex.
	 */
	public function dataHex(): string
	{
		if(empty($this->dataBin))
			return "0x";
		return "0x".bin2hex($this->dataBin);
	}

	/**
	 * Gets the binary data payload of the transaction.
	 *
	 * @return string The binary data.
	 */
	public function dataBin(): string
	{
		return $this->dataBin;
	}

	/**
	 * Gets the ECDSA 'v' value of the signature.
	 *
	 * @return OOGmp The 'v' value.
	 */
	public function v(): OOGmp
	{
		return $this->v;
	}

	/**
	 * Gets the ECDSA 'r' value of the signature.
	 *
	 * @return OOGmp The 'r' value.
	 */
	public function r(): OOGmp
	{
		return $this->r;
	}

	/**
	 * Gets the ECDSA 's' value of the signature.
	 *
	 * @return OOGmp The 's' value.
	 */
	public function s(): OOGmp
	{
		return $this->s;
	}
	/**
	 * Checks if the transaction is signed.
	 *
	 * @return bool True if signed, false otherwise.
	 */
	public function isSigned(): bool
	{
		return $this->signed;
	}

	/**
	 * Calculates the transaction hash.
	 *
	 * @return Hash The transaction hash.
	 * @throws EthBinderLogicException
	 */
	public function hash(): Hash
	{
		try {
			$bin = Keccak::hash($this->encodeBin(), 256, true);
			return Hash::fromBin($bin);
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * Calculates the hash used for signing the transaction.
	 *
	 * @param int|null $chainId Optional chain ID.
	 * @return Hash The signing hash.
	 * @throws InvalidLengthException
	 * @throws EthBinderLogicException
	 */
	public function getSigningHash(?int $chainId): Hash
	{
		try {
			$data = $this->encodeBinForSigning($chainId);
			$bin  = Keccak::hash($data, 256, true);
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
		return Hash::fromBin($bin);
	}

	/**
	 * Signs the transaction.
	 *
	 * @param Key $key Private key for signing.
	 * @param int|null $chainId Optional chain ID.
	 * @return static
	 * @throws InvalidLengthException
	 * @throws EthBinderLogicException
	 */
	public function sign(Key $key, ?int $chainId): static
	{
		$this->chainId = $chainId;
		$sig = $key->sign($this->getSigningHash($chainId));
		$this->r = $sig->r;
		$this->s = $sig->s;
		$this->v = $this->calculateV($sig->v);
		$this->signed = true;
		return $this;
	}

	/**
	 * Calculates the recovery id (v) for signature accounting for EIP155 (replay protection).
	 *
	 * @param OOGmp $recovery Recovery id before chain id calculations.
	 * @return OOGmp Final recovery id.
	 */
	public function calculateV(OOGmp $recovery): OOGmp
	{
		if($this->chainId === null)
			return $recovery->add(27);
		return $recovery
			->mod(2)
			->add($this->chainId * 2)
			->add(35);
	}

	/**
	 * Estimates gas and fee values using from RPC, trying to use conservative values.
	 * This invalidates signature if data differs from existing data
	 *
	 * @param AbstractRPC $rpc The RPC client.
	 * @param Address $from The sender address.
	 * @return static The updated Transaction object.
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 */
	public function useRpcEstimates(AbstractRPC $rpc, Address $from): static
	{
		return $this->useRpcEstimatesWithBump($rpc, $from, 0, 0);
	}

	/**
	 * Gets the address where the contract will be deployed if it's deploy transaction. If it is
	 * not deploy transaction, it will return null address - Address::NULL()
	 *
	 * @return Address Address where contract will be deployed.
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 */
	public function deployAddress(): Address
	{
		if(!$this->isSigned())
			return Address::NULL();
		if($this->to !== null)
			return Address::NULL();

		try {
			$bin = substr(
				Keccak::hash(
					Encoder::encodeBin([[$this->ecRecover()->toBin(), $this->nonce]]),
					256,
					true
				),
				12
			);
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
		return Address::fromBin($bin);
	}

	/**
	 * Gets the signature in wrapper object.
	 *
	 * @return Signature The signature details.
	 */
	public function signature(): Signature
	{
		$s = new Signature();
		$s->v = $this->v;
		$s->r = $this->r;
		$s->s = $this->s;
		return $s;
	}

	/**
	 * Sets the signature. Note that there is no guarantee the signature will work correctly.
	 * This is for advanced use only. Ensure to properly account for EIP 155 in signature's V.
	 *
	 * @return static The updated Transaction object.
	 */
	public function setSignature(Signature $s): static
	{
		$this->v = $s->v;
		$this->r = $s->r;
		$this->s = $s->s;
		return $this;
	}

	/**
	 * Convenience function that fetches nonce from RPC and places it into transaction.
	 *  If new nonce mismatches currently set nonce, it invalidates signature.
	 *
	 * @param Address $from Address of which to set next nonce.
	 * @param AbstractRPC $rpc RPC to query transaction count from.
	 * @return Transaction self for chainable API.
	 * @throws EthBinderRuntimeException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function nonceFromRPC(Address $from, AbstractRPC $rpc): static
	{
		return $this->setNonce($rpc->ethGetTransactionCount($from)->toInt());
	}
}
