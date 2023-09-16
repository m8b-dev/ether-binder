<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayKnownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayUnknownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiTuple;
use M8B\EtherBinder\Contract\AbiTypes\AbstractABIValue;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

/**
 * ABIEncoder handles the encoding and decoding of ABI data in Ethereum smart contracts.
 *
 * @author DubbaThony
 */
class ABIEncoder
{
	/**
	 * Encodes ABI data for an Ethereum smart contract function. It returns binary blob which can be `bin2hex()`-ed
	 * for presentation purposes.
	 *
	 * @param string $signature The function signature including the function name and its parameters.
	 * @param array $data The data to encode.
	 * @param bool $withTransactionSignature Whether to prepend the function selector hash to the encoded data.
	 * @return string The ABI-encoded binary blob.
	 * @throws EthBinderLogicException Thrown if function name end is not found or indicates other bug.
	 * @throws EthBinderArgumentException Thrown if validation of signature fails.
	 */
	public static function encode(string $signature, array $data, bool $withTransactionSignature = true): string {
		self::validateSignature($signature);
		$fnNameEnd = strpos($signature, "(");
		if($fnNameEnd === false)
			throw new EthBinderLogicException("function name end not found");

		$mainFn = self::createEncodingFromType(substr($signature, $fnNameEnd), $data);

		if($withTransactionSignature) {
			$signature = str_replace("int,", "int256,", $signature);
			$signature = str_replace("int)", "int256)", $signature);
			$signature = str_replace("int]", "int256]", $signature);
			try {
				$signatureHash = Keccak::hash($signature, 256, true);
			} catch(Exception $e) {
				throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
			}
			return substr($signatureHash, 0, 4).$mainFn->encodeBin();
		}
		return $mainFn->encodeBin();
	}

	/**
	 * Decodes ABI data returned from an Ethereum smart contract function using function signature (which can have
	 * fictional function name, but that's optional)
	 *
	 * @param string $signature The function signature including the function name and its parameters.
	 * @param string $dataBin The ABI-encoded binary data.
	 * @return AbiTuple The decoded ABI data as an AbiTuple.
	 * @throws EthBinderLogicException Thrown if function name end is not found or if other logic error occurs.
	 * @throws EthBinderArgumentException Thrown if validation of signature fails.
	 */
	public static function decode(string $signature, string $dataBin): AbiTuple
	{
		self::validateSignature($signature);
		$fnNameEnd = strpos($signature, "(");
		if($fnNameEnd === false)
			throw new EthBinderLogicException("function name end not found");

		$tupl = self::createEncodingFromType(substr($signature, $fnNameEnd), null);
		$tupl->decodeBin($dataBin, 0);
		/** @var AbiTuple $tupl */
		// AbiTuple will always be top-level, and it's a bug if it won't.
		return $tupl;
	}

	/**
	 * @param string $type
	 * @param $data
	 * @return AbstractABIValue
	 * @throws EthBinderArgumentException
	 * @throws EthBinderLogicException
	 */
	private static function createEncodingFromType(string $type, $data): AbstractABIValue
	{
		if(str_ends_with($type, "[]")) {
			$elementType = substr($type, 0, -2);
			$arrayObj    = new AbiArrayUnknownLength(self::createEncodingFromType($elementType, null));
			if($data === null)
				return $arrayObj;
			foreach($data as $element) {
				$arrayObj[] = self::createEncodingFromType($elementType, $element);
			}
			return $arrayObj;
		}  elseif(str_ends_with($type, "]")) {
			$openBracketPos  = strrpos($type, "[");
			$closeBracketPos = strrpos($type, "]");
			if($closeBracketPos === false)
				throw new EthBinderLogicException("type $type does not contain [, this should be caught by validator, but wasn't");
			$elementType = substr($type, 0, $openBracketPos);
			$length      = (int) substr($type, $openBracketPos + 1, $closeBracketPos - $openBracketPos - 1);

			if($length === 0) {
				throw new EthBinderArgumentException("got 0 length array that's typed for known length array");
			}

			$arrayObj = new AbiArrayKnownLength($length, self::createEncodingFromType($elementType, null));

			// Verify that data length matches expected length for fixed-size arrays
			if($data !== null && count($data) !== $length) {
				throw new EthBinderLogicException("Data length doesn't match fixed array size");
			}

			if($data === null)
				return $arrayObj;

			foreach($data as $i => $element) {
				$arrayObj[$i] = self::createEncodingFromType($elementType, $element);
			}
			return $arrayObj;
		} elseif(str_starts_with($type, "(")) {
			$tupleObj     = new AbiTuple();
			$elementTypes = self::explodeTuple($type);
			foreach($elementTypes as $index => $elementType) {
				$itm        = $data === null ? null : $data[$index];
				$tupleObj[] = self::createEncodingFromType($elementType, $itm);
			}
			return $tupleObj;
		} else {
			return AbstractABIValue::parseValue($type, $data);
		}
	}

	/**
	 * Splits a tuple type into its constituent types, preserving child tuples as single type. Input must start with
	 * `(` and end with `)`, without function name etc. Example input and output:
	 * "(uint256,(uint8,uint16)[],bytes)" => ["uint256", "(uint8,uint16)[]", "bytes"]
	 *
	 * @param string $types The tuple types string.
	 * @return array An array of the constituent types.
	 * @throws EthBinderArgumentException Thrown if provided tuple is invalid.
	 */
	public static function explodeTuple(string $types): array {
		if($types[0] !== "(" || $types[strlen($types) - 1] !== ")")
			throw new EthBinderArgumentException("Provided invalid tuple");
		$types = substr($types, 1, -1);
		if(empty($types))
			return [];
		$result = [];
		$buffer = "";
		$count  = 0;

		foreach(str_split($types) AS $char) {
			if($char === "(") {
				$count++;
			} elseif ($char === ")") {
				$count--;
			}

			if($char === "," && $count === 0) {
				$result[] = $buffer;
				$buffer = "";
			} else {
				$buffer .= $char;
			}
		}

		if(!empty($buffer)) {
			$result[] = $buffer;
		}

		return $result;
	}

	/**
	 * @param string $signature
	 * @return void
	 * @throws EthBinderArgumentException thrown whenever validation fails
	 */
	private static function validateSignature(string $signature): void {
		$whitespace = strpos($signature, " ");
		if($whitespace !== false)
			throw new EthBinderArgumentException("signature contains white space, which renders it as invalid signature. Space was found in position $whitespace in signature '$signature'");

		$stack              = [];
		$numStack           = [];
		$passedFunctionName = false;

		for($i = 0, $len = strlen($signature); $i < $len; $i++) {
			$char = $signature[$i];

			if($char === "(") {
				$passedFunctionName = true;
				$stack[]            = $char;
			} elseif($char === ")") {
				if(end($stack) !== "(") {
					throw new EthBinderArgumentException("Mismatched brackets in the signature");
				}
				array_pop($stack);
			} elseif($char === "[") {
				$stack[]    = $char;
				$numStack[] = "";
			} elseif($char === "]") {
				if(end($stack) !== "[") {
					throw new EthBinderArgumentException("Mismatched brackets in the signature");
				}
				array_pop($stack);
				$num = array_pop($numStack);
				if($num !== "" && !ctype_digit($num)) {
					throw new EthBinderArgumentException("Invalid characters between brackets");
				}
			} elseif(end($stack) === "[") {
				$numStack[count($numStack) - 1] .= $char;
			}
		}

		if(!empty($stack)) {
			throw new EthBinderArgumentException("Mismatched brackets in the signature");
		}

		if(!$passedFunctionName) {
			throw new EthBinderArgumentException("No function name detected in the signature");
		}
	}
}
