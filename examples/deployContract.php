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

// Assuming that you have ./erc20.abi.json and ./erc20.bin.hex
// first, run ABIGen.
// in terminal, use this command:
//
// ./vendor/bin/abigen.php --abi erc20.abi.json --bin erc20.bin.hex --fqcn \\Contracts\\ERC20 --out ./src/contracts
//
// bear in mind, that fqcn and out directory should match your autoloader setup, and that you can ommit --bin parameter,
// but this will create bindings without deployment ability.

$transaction = \Contracts\ERC20::deployNewERC20($rpc, $key, "Ether Binder Token", "TEST");
echo "Deployed ERC 20 token to " . $transaction->deployAddress()->checksummed() . PHP_EOL;
echo "with transaction hash " . $transaction->hash()->toHex() . PHP_EOL;
