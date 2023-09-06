<?php

namespace M8B\EtherBinder\Contract;

use kornrunner\Keccak;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayKnownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiArrayUnknownLength;
use M8B\EtherBinder\Contract\AbiTypes\AbiTuple;
use M8B\EtherBinder\Contract\AbiTypes\AbstractABIValue;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

class ABIEncoder
{
	/**
	 * @param string $signature
	 * @param array $data
	 * @return string
	 * @throws EthBinderLogicException
	 */
	public static function encode(string $signature, array $data): string {
		self::validateSignature($signature);
		$mainFn = new AbiTuple();

		$types = explode(",", substr($signature, strpos($signature, "(") + 1, -1));

		if (count($types) !== count($data)) {
			throw new EthBinderLogicException("Mismatch between signature and data array");
		}

		foreach ($types as $index => $type) {
			$mainFn[] = self::createFromType($type, $data[$index]);
		}

		$signatureHash = Keccak::hash($signature, 256, true);
		return substr($signatureHash, 0, 4).$mainFn->encodeBin();
	}

	/**
	 * @param string $type
	 * @param $data
	 * @return AbstractABIValue
	 */
	private static function createFromType(string $type, $data): AbstractABIValue
	{
		if(str_ends_with($type, "[]")) {
			$elementType = substr($type, 0, -2);
			$arrayObj = new AbiArrayUnknownLength();
			foreach ($data as $element) {
				$arrayObj[] = self::createFromType($elementType, $element);
			}
			return $arrayObj;
		} elseif (str_contains($type, "[") && str_contains($type, "]")) {
			$openBracketPos = strpos($type, "[");
			$closeBracketPos = strpos($type, "]");
			$elementType = substr($type, 0, $openBracketPos);
			$length = (int) substr($type, $openBracketPos + 1, $closeBracketPos - $openBracketPos - 1);

			// If length is zero, it means it's an array with unknown length
			if ($length === 0) {
				$arrayObj = new AbiArrayUnknownLength();
			} else {
				$arrayObj = new AbiArrayKnownLength($length);
			}

			foreach ($data as $element) {
				$arrayObj[] = self::createFromType($elementType, $element);
			}
			return $arrayObj;
		} elseif (str_starts_with($type, "(")) {
			$tupleObj = new AbiTuple();
			$elementTypes = explode(",", rtrim(ltrim($type, "("), ")"));
			foreach ($elementTypes as $index => $elementType) {
				$tupleObj[] = self::createFromType($elementType, $data[$index]);
			}
			return $tupleObj;
		} else {
			return AbstractABIValue::parseValue($type, $data);
		}
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

		for ($i = 0, $len = strlen($signature); $i < $len; $i++) {
			$char = $signature[$i];

			if ($char === "(") {
				$passedFunctionName = true;
				$stack[] = $char;
			} elseif ($char === ")") {
				if (end($stack) !== "(") {
					throw new EthBinderLogicException("Mismatched brackets in the signature");
				}
				array_pop($stack);
			} elseif ($char === "[") {
				$stack[] = $char;
				$numStack[] = "";
			} elseif ($char === "]") {
				if (end($stack) !== "[") {
					throw new EthBinderLogicException("Mismatched brackets in the signature");
				}
				array_pop($stack);
				$num = array_pop($numStack);
				if ($num !== "" && !ctype_digit($num)) {
					throw new EthBinderLogicException("Invalid characters between brackets");
				}
			} elseif (end($stack) === "[") {
				$numStack[count($numStack) - 1] .= $char;
			}
		}

		if (!empty($stack)) {
			throw new EthBinderLogicException("Mismatched brackets in the signature");
		}

		if (!$passedFunctionName) {
			throw new EthBinderLogicException("No function name detected in the signature");
		}
	}
}
