# Ether Binder ⚡ Your PHP Gateway to Ethereum

Unchain the full potential of Ethereum in PHP. Send transactions, tap into RPCs, and even generate ABI bindings just
like you would with [geth's abigen](https://geth.ethereum.org/docs/tools/abigen). Built for modern PHP 8.2!

## 🚀 Features
- **Modern PHP**: Utilizes PHP 8.2 features for cleaner, more robust code.
- **ABIGen**: A PHP counterpart to geth's ABIGen for contract bindings.
- **Ethereum Types**: Custom objects like `Hash`, `Address`, and more.
- **RPC Support**: Range of RPC methods to talk to Ethereum nodes.
- **Strongly Typed**: No more guessing games. Type hinting all the way.

## 🔧 Requirements
- PHP 8.2+
- Composer 2.2+

## 💾 Installation

Just use Composer. Install the alpha version for now.

```shell
composer require m8b/ether-binder:v0.0.5-alpha
```

## 📖 Documentation

- **Quick Start**: Grab code snippets from [examples](examples).
- **Read About Components**: Check out the [docs](https://m8b-dev.github.io/ether-binder/) for the full docs.

## 🚧 Status

> **Caution**: The library is still in its early stages. Perfect for tinkering but not ready for prime time.

### Feature Board

| feature                                   | status                     |
|-------------------------------------------|----------------------------|
| RPC HTTP                                  | ✅ Done                     |
| Transactions, serializing / deserializing | ✅ Done                     |
| RLP encoder / decoder                     | ✅ Done                     |
| RPC Eth_                                  | ✅ Done                     |
| Signing txn                               | ✅ Done                     |
| RPC Net_                                  | ✅ Done                     |
| RPC Web3_                                 | ✅ Done                     |
| Wallet (pk raw)                           | ✅ Done                     |
| Wallet (pk mnemonic)                      | ✅ Done                     |
| Signing msg                               | ✅ Done                     |
| Contract bindings (akin to abigen)        | ✅ Done                     |
| Documentation (in-code)                   | ✅ Done                     |
| Documentation (generated from in-code)    | ✅ Done                     |
| Documentation (standalone)                | ✅ Done                     |
| RPC Net_ filters                          | ✅ Done                     |
| Wallet (pk .json)                         | ⚠️ Planned as separate lib |

### A Note on JSON Keystore

Due to performance constraints with scrypt in PHP, JSON keystore support is planned as a separate library.
[Learn More](https://m8b-dev.github.io/ether-binder/jsonkeystore.md)

## 📜 License
Mozilla Public License 2.0

---

Missing something? [Post an issue](https://github.com/m8b-dev/ether-binder/issues).
