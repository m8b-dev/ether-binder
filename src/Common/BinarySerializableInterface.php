<?php

namespace M8B\EtherBinder\Common;

interface BinarySerializableInterface
{
	public function toHex(): string;
	public function toBin(): string;
	public static function fromHex(string $hex): static;
	public static function fromBin(string $bin): static;
}