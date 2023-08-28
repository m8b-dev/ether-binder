<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

require_once "../vendor/autoload.php";

const PRIVATE_KEY_HEX  = "0x...";
const JSON_RPC_ADDRESS = "https://...";

// initialize private key
$key = \M8B\EtherBinder\Crypto\Key::fromHex(PRIVATE_KEY_HEX);

// connect to RPC. Right now only http[s]:// RPC is supported
$rpc = new \M8B\EtherBinder\RPC\HttpRPC(JSON_RPC_ADDRESS);

// to send EIP-1559 transaction, use LondonTransaction() instead of LegacyTransaction. Note you will need to fill in base fee / tip fee cap
$txn = new \M8B\EtherBinder\Common\LegacyTransaction();

// so-called "cancel" transaction, transaction to self, with 0 value.
$txn->setTo($key->toAddress())
	->setValue(new \M8B\EtherBinder\Utils\OOGmp(0))
	->setGasLimit(21000)
	->setNonce($rpc->ethGetTransactionCount($key->toAddress())->toInt())
	->setGasPrice($rpc->ethGasPrice())
	->sign($key, $rpc->ethChainID());
$hash = $txn->hash();

var_dump("raw transaction RLP " . $txn->encodeHex());
var_dump("sending transaction " . $hash->toHex());
if($rpc->ethSendRawTransaction($txn)->toBin() !== $hash->toBin())
	throw new Exception("got different hash from remote");
var_dump("OK");