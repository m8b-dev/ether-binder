<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use kornrunner\Keccak;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayKnownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayUnknownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiTuple;
use M8B\EtherBinder\Contract\AbiTypes\AbstractABIValue;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

class ABIEncoder
{
	/**
	 * @param string $signature
	 * @param array $data
	 * @param bool $withTransactionSignature
	 * @return string
	 * @throws EthBinderLogicException
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
			$signatureHash = Keccak::hash($signature, 256, true);
			return substr($signatureHash, 0, 4).$mainFn->encodeBin();
		}
		return $mainFn->encodeBin();
	}

	public static function decode(string $signature, string $dataBin): array
	{
		self::validateSignature($signature);
		$fnNameEnd = strpos($signature, "(");
		if($fnNameEnd === false)
			throw new EthBinderLogicException("function name end not found");

		return self::createEncodingFromType(substr($signature, $fnNameEnd), null)->decodeBin($dataBin);
	}

	/**
	 * @param string $type
	 * @param $data
	 * @return AbstractABIValue
	 */
	private static function createEncodingFromType(string $type, $data): AbstractABIValue
	{
		if(str_ends_with($type, "[]")) {
			$elementType = substr($type, 0, -2);
			$arrayObj = new AbiArrayUnknownLength();
			if($data === null)
				return $arrayObj;
			foreach($data as $element) {
				$arrayObj[] = self::createEncodingFromType($elementType, $element);
			}
			return $arrayObj;
		}  elseif(str_ends_with($type, "]")) {
			$openBracketPos = strrpos($type, "[");
			$closeBracketPos = strrpos($type, "]");
			if($closeBracketPos === false)
				throw new EthBinderLogicException("type $type does not contain [, this should be cought by validator, but wasn't");
			$elementType = substr($type, 0, $openBracketPos);
			$length = (int) substr($type, $openBracketPos + 1, $closeBracketPos - $openBracketPos - 1);

			// If length is zero, it means it's an array with unknown length
			if($length === 0) {
				$arrayObj = new AbiArrayUnknownLength();
			} else {
				$arrayObj = new AbiArrayKnownLength($length);

				// Verify that data length matches expected length for fixed-size arrays
				if($data !== null && count($data) !== $length) {
					throw new EthBinderLogicException("Data length doesn't match fixed array size");
				}
			}

			if($data === null)
				return $arrayObj;

			foreach($data as $element) {
				$arrayObj[] = self::createEncodingFromType($elementType, $element);
			}
			return $arrayObj;
		} elseif(str_starts_with($type, "(")) {
			$tupleObj = new AbiTuple();
			$elementTypes = self::explodeTuple($type);
			foreach($elementTypes as $index => $elementType) {
				$itm = $data === null ? null : $data[$index];
				$tupleObj[] = self::createEncodingFromType($elementType, $itm);
			}
			return $tupleObj;
		} else {
			return AbstractABIValue::parseValue($type, $data);
		}
	}

	public static function explodeTuple(string $types): array {
		if($types[0] !== "(" || $types[strlen($types) - 1] !== ")")
			throw new EthBinderArgumentException("Provided invalid tuple");
		$types = substr($types, 1, -1);
		if(empty($types))
			return [];
		$result = [];
		$buffer = "";
		$count = 0;

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
	 * @throws EthBinderLogicException
	 */
	private static function validateSignature(string $signature): void {
		$stack = [];
		$numStack = [];
		$passedFunctionName = false;

		for($i = 0, $len = strlen($signature); $i < $len; $i++) {
			$char = $signature[$i];

			if($char === "(") {
				$passedFunctionName = true;
				$stack[] = $char;
			} elseif($char === ")") {
				if(end($stack) !== "(") {
					throw new EthBinderLogicException("Mismatched brackets in the signature");
				}
				array_pop($stack);
			} elseif($char === "[") {
				$stack[] = $char;
				$numStack[] = "";
			} elseif($char === "]") {
				if(end($stack) !== "[") {
					throw new EthBinderLogicException("Mismatched brackets in the signature");
				}
				array_pop($stack);
				$num = array_pop($numStack);
				if($num !== "" && !ctype_digit($num)) {
					throw new EthBinderLogicException("Invalid characters between brackets");
				}
			} elseif(end($stack) === "[") {
				$numStack[count($numStack) - 1] .= $char;
			}
		}

		if(!empty($stack)) {
			throw new EthBinderLogicException("Mismatched brackets in the signature");
		}

		if(!$passedFunctionName) {
			throw new EthBinderLogicException("No function name detected in the signature");
		}
	}
}
