# Eth Binder

The goal is to get something that will be like ethers.js for PHP.

# Status

Early. Don't use yet for production purposes. Feel free to experiment around.

The headline feature to be used is contract binding, which you can look at `bin/abigen.php --help`

# Status

| feature                                   | status      |
|-------------------------------------------|-------------|
| RPC HTTP                                  | done        |
| Transactions, serializing / deserializing | done        |
| RLP encoder / decoder                     | done        |
| RPC Eth_                                  | done        |
| Signing txn                               | done        |
| RPC Net_                                  | in progress |
| RPC Web3_                                 | done        |
| Wallet (pk raw)                           | done        |
| Wallet (pk mnemonic)                      | done        |
| Signing msg                               | done        |
| Contract bindings (akin to abigen)        | in progress |
| RPC Net_ filters                          | planned     |
| Documentation (in-code)                   | in progress |
| Documentation (generated from in-code)    | planned     |
| Documentation (standalone)                | wishlist    |
| RPC Wss                                   | wishlist    |
| RPC Shh_                                  | not planned |
| Wallet (pk .json)                         | dropped     |

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
