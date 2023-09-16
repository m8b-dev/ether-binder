<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

require_once "../vendor/autoload.php";

const PRIVATE_KEY_HEX  = "0x...";
const JSON_RPC_ADDRESS = "https://...";
const ERC20_ADDRESS    = "0x....";
const RECIPIENT        = "0x....";

// initialize private key
$key = \M8B\EtherBinder\Crypto\Key::fromHex(PRIVATE_KEY_HEX);

// connect to RPC. Right now only http[s]:// RPC is supported
$rpc = new \M8B\EtherBinder\RPC\HttpRPC(JSON_RPC_ADDRESS);

// Assuming that you have generated contract \\Contracts\\ERC20

$address     = \M8B\EtherBinder\Common\Address::fromHex(ERC20_ADDRESS);
$token       = new \Contracts\ERC20($rpc, $address);

// to call getter functions (such as `view` or `pure`), just call such function on the object.
// If the function returns more than one parameter, an array will be created. If there is struct returned, appropriate
// tuple will be returned (tuple object from class generated via ABIGen). Tuple has simple getters, and array access.
print_r([
	"name"     => $token->name(),
	"symbol"   => $token->symbol(),
	"decimals" => $token->decimals()
]);
// each function call above, calls RPC, prepares input from function signature and parameters and parses output.

// If you didn't instantiate contract with private key, you can load it later or sign transactions manually (or have
// custom scheme, for example if you want to have the transaction signed somewhere else). You can also load/unload
// address to use estimations from.

$recipient = \M8B\EtherBinder\Common\Address::fromHex(RECIPIENT);
$amount    = \M8B\EtherBinder\Utils\WeiFormatter::fromHuman("10");
try {
	$transaction = $token->transfer($recipient, $amount);
	die("expected exception");
} catch(\M8B\EtherBinder\Exceptions\EthBinderLogicException $e) {
	echo "Estimations should fail - cannot transfer from null address. Caught: " . $e->getMessage();
}

// If you want to sign it somewhere else, you still need to know address of the key. That's becouse underlying estimate
// gas function must be called.
$token->setFallbackFrom($key->toAddress());
$transaction = $token->transfer($recipient, $amount);
// you can either extract signing hash (if you sign manually, you know that on cryptography level it boils down to getting
// hash to sign). Bear in mind that hash() is not signingHash, they differ.
$hash = $transaction->getSigningHash($rpc->ethChainID());

// this happenes in another service, maybe secret store of your choice
$signature = $key->sign($hash);

// $signature gets transferred back. v, r, s are public properties, that you can spawn on your own
$transaction->setSignature($signature);

// now you are good to send transaction
// $rpc->ethSendRawTransaction($transaction);
// but instead, let's explore another way of signing transaction.

// This one is fairly straight-forward.
$transaction = $token->transfer($recipient, $amount)
	->sign($key, $rpc->ethChainID());
// And voilà! Transaction is ready to be sent.
// $rpc->ethSendRawTransaction($transaction);

// Instead, you can also have binding sign transaction for you, without sending it.
$token->unsetFallbackFrom(); // optional
$token->loadPrivK($key);
// noSend will disable sending transaction. When transaction is generated, and private key is loaded, by default, it
//  will also send the transaction. To prevent this behaviour, set public property `noSend` to true.
$token->noSend = true;
$transaction = $token->transfer($recipient, $amount);
// $rpc->ethSendRawTransaction($transaction);

// And of course, you can just use default behaviour and rely on binding to sign and send without intervention
$token->noSend = false; // false is the default

$transaction = $token->transfer($recipient, $amount);
echo "Sent transaction for 10 tokens to $recipient. Transaction hash is " . $transaction->hash()->toHex() . PHP_EOL;

// Now, the transaction was sent and is waiting for confirmation. In case you have problems with fees to be too low,
// there is per-bind configuration that allows you to pay more for transaction. Let's say that we are in huge hurry
// and want to pay 50% extra for confirmation time. For legacy transaction this bumps gas price, for EIP1559
// transactions, this rises both base fee cap and tip price for any new transaction
\Contracts\ERC20::$transactionFeesPercentageBump = 50;
