# Transactions

Concrete Transaction types extend abstract class Transaction, and this should be used for typing, unless explicit
specific type of transaction is expected.

## Supported types

Ether binder right now supports Legacy transactions and London transactions.
Blob transactions will be added when the spec will be finalized.
Access list transactions will not be supported for generation in bindings, but support for parsing them from RPC
is planned. Right now EIP2930 Access List transaction cannot be instantiated (Legacy transaction but Access List one).
Bear in mind, London transactions support Access List, but again, access list is not filled in from bindings.

## Transaction types


There is Enum for transaction types `TransactionType` that allows to select transaction type from type number (like
in post-london format of transaction encoding - the first number of RLP, before transaction array), and it allows to
instantiate it.

RPC has helper method to check if EIP1559 transaction could be used on current chain.

# Preparing Transaction

To prepare transaction, create new transaction object, and fill in its fields using chainable method calls.
Note: returned transaction is not clone, as it's the case with OOGmp, the output can be ignored.

Complete "bones" for transaction code are as follows:
```php
$key = \M8B\EtherBinder\Crypto\Key::fromHex("0x.....");
$rpc = new \M8B\EtherBinder\RPC\HttpRPC("https://...");

$txn = new \M8B\EtherBinder\Common\LegacyTransaction();

$txn
    ->setTo($key->toAddress())
	->setValue(new \M8B\EtherBinder\Utils\OOGmp(0))
	->setGasLimit(21000)
	->setNonce($rpc->ethGetTransactionCount($key->toAddress())->toInt())
	->setGasPrice($rpc->ethGasPrice())
	->sign($key, $rpc->ethChainID());
```

# RLP Decoding and Encoding

Every transaction has 2 method for encoding and 2 methods for decoding. As in most places in the library, you get to
choose if you want binary blob, or hexadecimal representation.

```php
$binary = $txn->encodeBin();
// don't echo binary to terminal, it may break it
$hex = $txn->encodeHex();
echo "Encoded transaction: " . $hex . PHP_EOL;
```

Please note that there is also encoding for signing, which differs from encoding of entire transaction (like encoding 
for getting transaction hash)

```php
$chainId = $rpc->ethChainID();
$txn->encodeHexForSigning($chainId);
```

# Other utilities

There are other utility functions, which should be self-explanatory.
```php
$txn->hash()->toHex();
$txn->getBaseFeeCap()->toString(); // only for London Transactions
$txn->getGasFeeTip()->toString(); // only for London Transactions
$txn->totalGasPrice(); // for London base + tip, for legacy gas price
$txn->to()->checksummed();
$txn->isReplayProtected(); // is EIP155
$txn->useRpcEstimates($rpc, $key->toAddress()); // use RPC to fill in gas limit and fees. Target low.
$txn->useRpcEstimatesWithBump($rpc, $key->toAddress(), 10, 15); // As above but bump gas limit by (here) 10%, and gas prices 15%
$txn->nonce();
$txn->transactionType(); // enum
```

