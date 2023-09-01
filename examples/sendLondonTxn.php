<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

require_once "../vendor/autoload.php";

const PRIVATE_KEY_HEX = "";
const JSON_RPC_ADDRESS = "https://goerli.infura.io/v3/";

$key = \M8B\EtherBinder\Crypto\Key::fromHex(PRIVATE_KEY_HEX);
$rpc = new \M8B\EtherBinder\RPC\HttpRPC(JSON_RPC_ADDRESS);
$txn = new \M8B\EtherBinder\Common\LondonTransaction();

// so-called "cancel" transaction, transaction to self, with 0 value.
$txn->setTo($key->toAddress())
	->setDataBin("hello, world!") // note: dataBin expects binary input. For hex input use setDataHex(). This should display "hello, world!" on block explorers
	->setValue(new \M8B\EtherBinder\Utils\OOGmp(0))
	->setNonce($rpc->ethGetTransactionCount($key->toAddress())->toInt())
	->useRpcEstimates($rpc, $key->toAddress()) // will not work correctly on non-london chain
	->sign($key, $rpc->ethChainID());

$hash = $txn->hash();

var_dump("sending transaction " . $hash->toHex());
if($rpc->ethSendRawTransaction($txn)->toBin() !== $hash->toBin())
	throw new Exception("got different hash from remote");
var_dump("OK");