<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Utils;

use M8B\EtherBinder\Common\Block;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Exceptions\RpcException;
use M8B\EtherBinder\Misc\EIP1559Config;
use M8B\EtherBinder\RPC\AbstractRPC;

/**
 * Functions is an abstract utility class that holds static utility methods.
 *
 * @author DubbaThony
 */
abstract class Functions {

	/**
	 * Validates the length of a hexadecimal string. In case the length is invalid, exception is risen.
	 *
	 * @param string $hex The hex string.
	 * @param int $len The expected length.
	 * @throws InvalidHexException
	 * @throws InvalidHexLengthException
	 */
	public static function mustHexLen(string $hex, int $len): void
	{
		if(str_starts_with($hex, "0x")){
			$hex = substr($hex, 2);
		}

		if(!ctype_xdigit($hex))
			throw new InvalidHexException("got unexpected character in hex");
		if(strlen($hex) != $len)
			throw new InvalidHexLengthException($len, strlen($hex));
	}

	/**
	 * Left-pads a hex string to a specific length.
	 *
	 * @param string $hex The hex string.
	 * @param int $padTo The length to pad to.
	 * @param bool $closestMultiplier Whether to pad to the closest multiple of $padTo, instead of just to $padTo
	 * @return string The padded hex string.
	 */
	public static function lPadHex(string $hex, int $padTo, bool $closestMultiplier = true): string
	{
		$has0x = false;
		if(str_starts_with($hex, "0x")) {
			$hex = substr($hex, 2);
			$has0x = true;
		}

		if(strlen($hex) > $padTo && !$closestMultiplier) {
			return ($has0x ? "0x":"") . $hex;
		}

		if($closestMultiplier) {
			$targetLength = ceil(strlen($hex) / $padTo) * $padTo;
		} else {
			$targetLength = $padTo;
		}

		$missingZeroes = $targetLength - strlen($hex);
		$paddedHex = str_repeat('0', $missingZeroes) . $hex;
		return ($has0x ? "0x" : "") . $paddedHex;
	}

	/**
	 * Converts an integer to a hex string.
	 *
	 * @param int $val The integer value.
	 * @param bool $with0x Whether to include the "0x" prefix.
	 * @return string The hex string.
	 */
	public static function int2hex(int $val, bool $with0x = true): string
	{
		return ($with0x ? "0x" : "").dechex($val);
	}

	/**
	 * Converts hex string to integer
	 *
	 * @param string $val The hexadecimal string
	 * @return int The integer value
	 * @throws EthBinderRuntimeException when number exceeds PHP_INT_MAX
	 */
	public static function hex2int(string $val): int
	{
		return (new OOGmp($val, 16))->toInt();
	}

	/**
	 * Returns worst case scenario base fee for block currentBlock + blocksAhead. Useful for estimating base fee for
	 * transactions. Since the fee is base fee, it shouldn't matter if it's overestimated, since consensus will prevent
	 * spending surplus
	 *
	 * @param Block $previous
	 * @param int $blocksAhead
	 * @param EIP1559Config|null $config
	 * @return OOGmp
	 */
	public static function getPessimisticBlockBaseFee(Block $previous, int $blocksAhead, ?EIP1559Config $config = null): OOGmp
	{
		$config = $config ?? EIP1559Config::sepolia();
		$next = static::getNextBlockBaseFee($previous, $config);
		if($blocksAhead <= 1)
			return $next;
		for($i = 1; $i < $blocksAhead; $i++) {
			$block                = new Block();
			$block->number        = $previous->number + $i;
			$block->gasLimit      = $previous->gasLimit;
			$block->gasUsed       = $block->gasLimit;
			$block->baseFeePerGas = $next;
			$next                 = static::getNextBlockBaseFee($block, $config);
		}
		return $next;
	}

	/**
	 * 0x tolerant, exceptions-compatible hex2bin() drop-in replacement, that never returns false, handles empty strings
	 * and prefixes "0" if odd amount of characters is spotted.
	 *
	 * @param string $hex
	 * @return string Binary blob
	 * @throws InvalidHexException
	 */
	public static function hex2bin(string $hex): string
	{
		$hex = str_starts_with($hex, "0x") ? substr($hex, 2) : $hex;
		if(strlen($hex) == 0)
			return "";
		if(!ctype_xdigit($hex))
			throw new InvalidHexException("got unexpected character in hex");
		if(strlen($hex) % 2 != 1)
			$hex = "0".$hex;
		return hex2bin($hex);
	}

	/**
	 * Calculates the base fee for the next block in an EIP1559 compatible chain.
	 *
	 * @param Block $previous The previous block.
	 * @param EIP1559Config $config The EIP1559 configuration. Only required field is $config->activationBlockNumber
	 * @return OOGmp The calculated base fee.
	 */
	public static function getNextBlockBaseFee(Block $previous, EIP1559Config $config): OOGmp
	{
		if($previous->number <= $config->activationBlockNumber) {
			return new OOGmp(EIP1559Config::INITIAL_BASE_FEE);
		}

		if(!$previous->isEIP1559()) {
			return new OOGmp(EIP1559Config::INITIAL_BASE_FEE);
		}

		$parentGasTarget = $previous->gasLimit / EIP1559Config::ELASTICITY_MULTIPLIER;
		if($parentGasTarget == $previous->gasUsed) {
			return $previous->baseFeePerGas;
		}

		if($previous->gasUsed > $parentGasTarget) {
			return (new OOGmp($previous->gasUsed - $parentGasTarget))
				->mul($previous->baseFeePerGas)
				->div($parentGasTarget)
				->div(EIP1559Config::BASE_FEE_CHANGE_DENOMINATOR)
				->max(1)
				->add($previous->baseFeePerGas);
		} else {
			return $previous->baseFeePerGas
				->sub(
					(new OOGmp($parentGasTarget - $previous->gasUsed))
					->mul($previous->baseFeePerGas)
					->div($parentGasTarget)
					->div(EIP1559Config::BASE_FEE_CHANGE_DENOMINATOR)
				)->max(0);
		}
	}

	/**
	 * This function will wait and block until the transaction is confirmed via repetatively checking receipt
	 *
	 * @param Transaction|Hash $txHash Hash of the transaction or signed transaction
	 * @param AbstractRPC $rpc RPC to use for transaction receipt pooling
	 * @param int $timeoutSeconds After how many seconds to give up
	 * @param int $intervalMS How long to wait between pooling attempts
	 *
	 * @return Receipt Transaction receipt
	 * @throws EthBinderRuntimeException if timeout happens. It does not mean the transaction will not get confirmed!
	 * @throws EthBinderLogicException
	 * @throws NotSupportedException
	 */
	public static function waitForTxReceipt(
		Transaction|Hash $txHash, AbstractRPC $rpc, int $timeoutSeconds = 60, int $intervalMS = 500): Receipt
	{
		if($txHash instanceof Transaction)
			$txHash = $txHash->hash();
		$startT = time();
		while(true) {
			try {
				return $rpc->ethGetTransactionReceipt($txHash);
			} catch(RpcException) {
				if($startT + $timeoutSeconds < time())
					throw new EthBinderRuntimeException("Timed out");
				usleep(1000*$intervalMS);
				continue;
			}
		}
	}
}
