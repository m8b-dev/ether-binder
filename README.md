# Ether Binder

A library to interact with Ethereum and Ethereum based blockchains. It allows to send transactions, wraps RPC, introduces
Ethereum-friendly types and includes ABIGen tool, similar to [geth's abigen](https://geth.ethereum.org/docs/tools/abigen).  
Library is typed and makes use of modern PHP 8.2 goodies (therefore, it requires PHP 8.2). 

# Requirements

- PHP 8.2+
- Composer 2.2+

# Installation

Just use composer ;)

```shell
composer require m8b/ether-binder 
```

# Documentation

For quick start, and some code to quickly copy, edit and use, see [examples](examples)

For documentation, see [docs](docs)

# Status

Early, but should be nearly feature-complete. The API shouldn't change *too much*
Don't use yet for production purposes. Feel free to experiment around.

The headline feature to be used is contract binding, which you can look at `bin/abigen.php --help`
This will generate bunch of files, for example if you have ERC20 contract:
```
ERC20EventApproval.php
ERC20EventOwnershipTransferred.php
ERC20EventTransfer.php
ERC20.php
```
There are also Tuple files, but ERC20 doesn't have tuples in ABI.
The documentation isn't done yet, but if you want to check it out early, here is example usage of ERC20 token (used
fqcn instead of `use` so it's clear what to import):
```shell
#first create bindings
./bin/abigen.php --abi ./erc20.abi.json --bin ./erc20.bin.hex --fqcn \\Test\\ERC20 -o ./src/Test
```
```php
$rpc = new \M8B\EtherBinder\RPC\HttpRPC("http://localhost:8545");
$key = \M8B\EtherBinder\Crypto\Key::fromHex(YOUR_PRIVATE_KEY_HEX);
// or use mnemonic:
$key = (new \M8B\EtherBinder\Wallet\MnemonicWallet(
	["your", "mnemonic", "words"],
	"optional passphrase. Note, that right now it doesn't reproduce identical private keys as different"
	." implementation, so treat it for time being as vendor locking. You can always export key into raw hex", //optional
	"m/44'/60'/0'/0/0",
	\M8B\EtherBinder\Wallet\MnemonicLanguage::ENGLISH
))->key();
// also you can generate new mnemonic
$wordsArray = \M8B\EtherBinder\Wallet\MnemonicWallet::genNew(/*words count default 24*/24 /*, language default english*/);

// deploy new token
$txn = \Test\ERC20::deployNewERC20($rpc, $key, "EthBinderToken", "TEST");
// each transaction have deployAddress(). If it's not deploy transaction, 0x000... will be returned
$tokAddr = $txn->deployAddress();
echo "ERC20 Address = " . $tokAddr->checksummed() . PHP_EOL;
// Functions:: is just helpers.
$receipt = \M8B\EtherBinder\Utils\Functions::waitForTxReceipt($txn->hash(), $rpc, 120, 250);
// check status
if($receipt->status === false)
    die("reverted");

$token = new \Test\ERC20($rpc, $tokAddr, /*optional for read only can be null*/$key);
$txn = $token->transfer(Address::fromHex("0x..."), \M8B\EtherBinder\Utils\WeiFormatter::fromHuman("123"));
echo "sent transaction, hash is " . $txn->hash()->toHex(/*false to remove 0x*/) . PHP_EOL;

$receipt = \M8B\EtherBinder\Utils\Functions::waitForTxReceipt($txn->hash(), $rpc, 120, 250);
// assuming this specific implementation emits only 1 Transfer event. It's up to ERC20 implementation
list($transfer) = \Test\ERC20::getEventsFromReceipt($receipt);
/** @var \Test\ERC20EventTransfer $transfer */
echo "ERC 20 transaction log: " . PHP_EOL . "  "
    ."from:      ". $transfer->getFrom()->checksummed(). PHP_EOL . "  "
    ."to:        ". $transfer->getTo()->checksummed(). PHP_EOL . "  "
    ."value wei: ". $transfer->getValue()
    ."value:     ". \M8B\EtherBinder\Utils\WeiFormatter::fromWei($transfer->getValue(), 4).PHP_EOL;
```

# Status

| feature                                   | status                  |
|-------------------------------------------|-------------------------|
| RPC HTTP                                  | done                    |
| Transactions, serializing / deserializing | done                    |
| RLP encoder / decoder                     | done                    |
| RPC Eth_                                  | done                    |
| Signing txn                               | done                    |
| RPC Net_                                  | done                    |
| RPC Web3_                                 | done                    |
| Wallet (pk raw)                           | done                    |
| Wallet (pk mnemonic)                      | done                    |
| Signing msg                               | done                    |
| Contract bindings (akin to abigen)        | done                    |
| RPC Net_ filters                          | planned                 |
| Documentation (in-code)                   | done                    |
| Documentation (generated from in-code)    | planned                 |
| Documentation (standalone)                | wishlist                |
| Wallet (pk .json)                         | planned as separate lib |

JSON keystore is dropped due to fact it requires scrypt. Scrypt kdf in pure php is extremly slow, and there are 2 solutions:
- supplying C build for it, and using FFI. This approach has flaw that different php enviroments have different CPU extensions,
  and running this without building for cpu extensions is going to be very slow too. Also that requires a cross-compile for all
  php distributions and having all the blobs in the repo.
- pecl package - that could solve that, but would mean library is unusable without pecl (composer require) or throwing an
  unexpected exception when trying to open JSON keystore, which may be hidden for dev if dev have it installed, and
  frustrated when it crashes on actual environment. Reasonable alternative would be addon to this library to handle
  this and require a [pecl extension](https://pecl.php.net/package/scrypt) in composer file.


## License

Mozilla Public License 2.0

In case there are missing headers in source code files, post an issue. 
