<?php

namespace M8B\EtherBinder\Contract;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;

class ABIEncoder
{
	protected array $types = [];
	protected string $buff = "";

	public function __construct(string $functionSignature)
	{
		$this->types = $this->parseSignature($functionSignature);
	}

	public static function encodeWithSig(string $functionSignature, array $data): string
	{
		$s = new static($functionSignature);
		return $s->encode($data);
	}

	protected function parseSignature(string $signature): array
	{
		$start = strpos($signature, "(");
		$end = strpos($signature, ")");
		if($start === false || $end === false)
			throw new \InvalidArgumentException("function signature must have exactly one ( and exactly one )");
		$start += 1;
		$end -= $start;
		if($end === 0)
			return [];
		return explode(",", substr($signature, $start, $end));
	}

	public function typeGetArrayDetails(string $type): array
	{
		$result = [];
		$start = false;
		$temp = '';
		$bracketCount = 0;

		for($i = 0; $i < strlen($type); $i++) {
			if($type[$i] === '[') {
				$bracketCount++;
				if ($start) {
					throw new EthBinderArgumentException("ABI encoder: got invalid type: found opening bracket without closing previous bracket");
				}
				$start = true;
				continue;
			}
			if($type[$i] === ']') {
				$bracketCount--;
				if (!$start) {
					throw new EthBinderArgumentException("ABI encoder: got invalid type: found closing bracket without opening bracket");
				}
				$start = false;
				$result[] = ($temp === '') ? -1 : (int)$temp;
				$temp = '';
				continue;
			}
			if($start) {
				$temp .= $type[$i];
			}
		}

		if ($bracketCount !== 0) {
			throw new EthBinderArgumentException("ABI encoder: got invalid type: ");
		}

		return $result;
	}

	public function encode(array $data): string
	{
		if(count($data) != count($this->types)) {
			throw new \InvalidArgumentException("data length does not match signature length");
		}

		foreach($data AS $k => $field) {

		}
	}
}