# Generating bindings

One of main features of EtherBinder is bindings (hence the name).
It is alternative of [geth's abigen](https://geth.ethereum.org/docs/tools/abigen) for PHP.

In essence, it creates typed classes that allow you to interact with smart contract on EVM chain.
The binding handles for you underlying complexity, mostly abi en/de-coding.

To create binding you need abi json file.
If you want to be able to deploy contract, you also need compiled byte code in hex format.
The combined format is not supported at the second.

Following examples will use this example ERC20 implementation:
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract Token is ERC20, Ownable {
    struct Test {
        uint256 foo;
        uint256 bar;
    }

    constructor(string memory name, string memory symbol) ERC20(name, symbol) {
        _mint(msg.sender, 8_000_000 * 10 ** 18);
    }

    function returnsTuple() external pure returns(Test memory) {
        Test memory t;
        t.foo = 123;
        t.bar = 456;
        return t;
    }

    function returnsArray() external pure returns(Test[] memory) {
        Test[] memory t;
        t[0] = Test(123,456);
        t[1] = Test(78,90);
        return t;
    }

    function returnsMultiple() external pure returns(Test memory, Test memory) {
        Test memory t;
        t.foo = 123;
        t.bar = 456;
        Test memory t2;
        t2.foo = 78;
        t2.bar = 90;
        return (t, t2);
    }
}
```

Assuming that you have built above contract and stored it's abi in `./erc20.abi.json` and bytecode in `./erc20.bin.hex`
(note: bytecode must be in hex format, not binary blob);
and assuming that you have ./src as your project root and want your binding to reside in `\Contracts` namespace,
you can call:
```shell
./vendor/bin/abigen.php --abi erc20.abi.json --bin erc20.bin.hex --fqcn \\Contracts\\ERC20 --out ./src/contracts
```

The used parameters are:
 - `--abi` - path to abi json file
 - `--bin` - optional path to bytecode file
 - `--fqcn` - fully qualified class name of root class of the binding. Events and tuples will derive their names from this name too.
    If it points to global namespace, warning will be emitted. Also bear in mind that shel will treat single `\` as escape character,
    so to pass namespace, you need to escape `\` with another `\`
 - `--out` - path to directory (if it doesn't exist, it will be created). Any file that will be generated will be overwritten,
    but directory itself will not be cleared.

Bear in mind that you need to take into account your autoloader setup when writing namespace and directory.

This should result in bindings being generated into `./src/contracts` with main class `./src/contracts/ERC20.php`

## Events limitation

Currently, event allows parsing event data into appropriate object (to `Event` classes). There is a rare
case of having events that emit indexed dynamic data such as strings, arrays or tuples. Solidity in such case
returns keccak256 hash of such data, not the data itself, making the data itself unrecoverable. If the ABIGen
stumbles upon such event, while generating bindings, it will throw NotSupportedException. Such events are not supported.
In pinch, it's OK to remove the event from ABI JSON manually, but of course, such events will not be parsed.

If this happens you get exception with explainer and exactly which event and it's type causes the problem, so you can
adjust ABI or contract.

# Usage notes

Usage code samples will assume you have defined private key and RPC
```php
$key = \M8B\EtherBinder\Crypto\Key::fromHex("0x...");
$rpc = new \M8B\EtherBinder\RPC\HttpRPC("https://...");
```

# Deploying contract

To deploy contract call static function `deployNewCLASS_NAME`. First 2 parameters are always `AbstractRpc` and private
`Key`. Rest of parameters depend on given contract as these are input for it.

```php
$transaction = \Contracts\ERC20::deployNewERC20($rpc, $key, "Ether Binder Token", "TEST");
echo "Deployed ERC 20 token to " . $transaction->deployAddress()->checksummed() . PHP_EOL;
echo "with transaction hash " . $transaction->hash()->toHex() . PHP_EOL;
```

# Instantiating contract binding

In most basic form, the instantiating requires only RPC and contract address:
```php
$address = \M8B\EtherBinder\Common\Address::fromHex(ERC20_ADDRESS);
$token   = new \Contracts\ERC20($rpc, $address);
```

You can also supply private key and/or fallback address while instantiating. If you supply key, it's address will be used
ass fallback, regardless if you set fallback address, or not. Supplying private keys allows binding to sign and send
transactions, while fallback address is used for estimations and calls (from field). Not supplying any of these will
set this field to null address (0x000...). Depending on contract logic, this may produce invalid estimations or throw
exceptions that wouldn't be thrown otherwise (for example reverting due to "insufficient balance")

To supply private key:
```php
$address = \M8B\EtherBinder\Common\Address::fromHex(ERC20_ADDRESS);
$token   = new \Contracts\ERC20($rpc, $address, $key);
```

Or when you already have contract instantiated:
```php
$token->loadPrivK($key);
```

To remove private key:
```php
$token->unloadPrivK();
```

To supply fallback address, and get unsigned transactions:
```php
$address = \M8B\EtherBinder\Common\Address::fromHex("0x....");
$token   = new \Contracts\ERC20($rpc, $address, null, $key->toAddress());
```

To add fallback on already instantiated contract:
```php
$token->unsetFallbackFrom();
```

To remove fallback on already instantiated contract:
```php
$token->setFallbackFrom($key->toAddress());
```

# Using read functions on contract

To read contract, you just need to call function of bound contract object with same name, and pass same params as in solidity.
```php
print_r([
	"name"     => $token->name(),
	"symbol"   => $token->symbol(),
	"decimals" => $token->decimals(),
	"balance"  => $token->balanceOf($key->toAddress())
]);
```

Tuples are supported and typed
```php
$tuple = $token->returnsTuple();
var_dump($tuple->getFoo()->eqal(123)) // true
```

Some functions return multiple variables. To show this, the example solidity code has `returnsMultiple` test function.
```php
list($tupleA, $tupleB) = $token->returnsMultiple();
var_dump($tupleA::class == \Contracts\ERC20TupleTest::class); // true
var_dump($tupleB::class == \Contracts\ERC20TupleTest::class); // true
var_dump($tupleA->getFoo()->eqal(123)) // true
```

# Using write functions

Write functions work similarly to read functions, but instead return transactions in different states. The state depends
on binding state, and you can read more about it in instantiation section. Transaction kind is dynamically determined
based on underlying chain (Legacy or London). 

```php
$recipient = \M8B\EtherBinder\Common\Address::fromHex("0x....");
$transaction = $token->transfer($recipient, \M8B\EtherBinder\Utils\WeiFormatter::fromHuman("100"));
```

If function is payable, first param accepts value wrapped in OOGmp (Ether Binder's big number object), and function
params start from second param.

# Reading events

Ether Binder has 3 ways to parse events.

One way is to parse a specific event from Log
```php
$hash = \M8B\EtherBinder\Common\Hash::fromHex("0x....");
$receipt = $rpc->ethGetTransactionReceipt($hash);
$event = \Contracts\ERC20EventTransfer::parseEventFromLog($receipt->logs[0]);
print_r([
    "from" => $event->getFrom()->checksummed(),
    "to" => $event->getTo()->checksummed(),
    "value" => $event->getValue()->toString()
]);
```

Second way is to parse all events from Receipt
```php
$hash = \M8B\EtherBinder\Common\Hash::fromHex("0x....");
$receipt = $rpc->ethGetTransactionReceipt($hash);
$events = \Contracts\ERC20::getEventsFromReceipt($receipt->logs);
var_dump($events);
```

Third way is to use filter binding.
**NOTE:** After getting constructed, it does not install filter on RPC, and therefore the events listening didn't start
yet. At this point you can additionally configure the object with `setFromBlock` and `setToBlock` methods to set filter's
start and end. To install filter either start fetching with `parseFetchNext` or call `installFilter`. Note that after doing
so, the `setFromBlock` and `setToBlock` methods will throw exceptions. To set new from / to values, instantiate new filter.

The binding for parameters accepts rpc and contract address, and then the indexed params from event. Event dependent
params can be null (to accept any event) or array (to set up OR filter for this variable). If array is provided, all
items must be of same type as single item typing, otherwise an exception will be thrown.

`fetchNext()` will return next known event, or null if no more events were found
 
```php
$recipient = \M8B\EtherBinder\Common\Address::fromHex("0x....");
$filter = new \M8B\EtherBinder\Test\ERC20FilterTransfer($rpc, $address, $key->toAddress(), null);
$filter->installFilter();
$token->transfer($recipient, \M8B\EtherBinder\Utils\WeiFormatter::fromHuman(1));

while(($transfer = $filter->fetchNext()) !== null) {
	echo "got event ".$transfer->getTo()->checksummed()." => ".$transfer->getFrom()
		.", val=".WeiFormatter::fromWei($transfer->getValue()->toString(), 5). PHP_EOL;
}
```


# Influencing gas prices of binding's transactions

Binding has static variable `AbstractContract::$transactionFeesPercentageBump`.

It is flat percentage bump, defaults to 0. For legacy transactions it influences gas price, and for post-London transactions
it influences both base fee cap and tip fee. If set to for example 10, it means the gas price will be 110% of what it 
would be at default.

Calculation is simple:
```
// pseudocode
estimatedFee = estimator()
estimatedFee = (100 + transactionFeesPercentageBump) / 100
```

