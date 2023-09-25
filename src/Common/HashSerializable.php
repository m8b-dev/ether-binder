<?php

namespace M8B\EtherBinder\Common;

/**
 * Hash serializable is interface to mark classes that can serialize themselves into blob of max size of hash (ie. fit
 * abi single slot that's 32 bytes)
 *
 * @author DubbaThony
 */
interface HashSerializable
{
	public function toHex(): string;
	public function toBin(): string;
	public static function fromHex(string $hex): static;
	public static function fromBin(string $bin): static;
}