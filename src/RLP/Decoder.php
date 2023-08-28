<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RLP;

class Decoder
{
	private string $dataBin;
	private int $pointer;


	protected function __construct(string $dataBin){
		$this->pointer = 0;
		$this->dataBin = $dataBin;
	}

	public static function decodeRLPHex(string $hex): array
	{
		if(str_starts_with($hex, "0x"))
			$hex = substr($hex, 2);
		return static::decodeRLPBin(hex2bin($hex));
	}

	public static function decodeRLPBin(string $bin): array
	{
		return (new static($bin))->decode();
	}

	protected function decode(): array
	{
		$return = [];
		while($this->pointer < strlen($this->dataBin)) {
			$return[] = $this->decodeEntry();
		}
		return $return;
	}

	protected function getByteStr(int $len): string
	{
		if($len < 0) throw new \LogicException("impossible read");
		if($len == 0) return "0x";
		$data = substr($this->dataBin, $this->pointer, $len);
		$this->pointer += $len;
		return "0x".bin2hex($data);
	}

	protected function getByte(int $len = 1): int
	{
		if($len <= 0 || $len > 8) throw new \LogicException("0 size read");
		if($len == 1) {
			$this->pointer++;
			return unpack("C", $this->dataBin[$this->pointer - 1])[1];
		}
		$data = substr($this->dataBin, $this->pointer, $len);
		$this->pointer += $len;
		return unpack("J", str_repeat("\0", 8-$len).$data)[1];
	}

	protected function decodeEntry(): array|string
	{
		$byte = $this->getByte();
		/*if($byte == 0x80) {
			return "0x";
		}
		if($byte == 0xc0) {
			return [];
		}*/

		if($byte <= 0x7f) {
			return "0x".dechex($byte);
		}
		if($byte <= 0xb7) {
			return $this->getByteStr($byte - 0x80);
		}
		if($byte <= 0xbf) {
			$len = $this->getByte($byte-0xb7);
			return $this->getByteStr($len);
		}
		if($byte <= 0xf7) {
			return $this->decodeArray($byte - 0xc0);
		}
		if($byte > 0xf7) {
			$len = $this->getByte($byte-0xf7);
			return $this->decodeArray($len);
		}
		throw new \LogicException("unreachable reached");
	}

	protected function decodeArray(int $size): array
	{
		$return = [];
		$start = $this->pointer;
		while($this->pointer < $start + $size)
			$return[] = $this->decodeEntry();
		return $return;
	}
}
