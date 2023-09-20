# Ethereum friendly types

Ether binder supports few types to allow easier and more convinient interactions with Ethereum blockchain.

All types below reside in \M8B\EtherBinder\Common namespace

## Transactions

Transaction class is split for abstract class that contains common logic between transactions and specific transaction
types, such as LegacyTransaction, AccessListTransaction and LondonTransaction (also known as Dynamic Fee transaction).
Decoding transaction should be done from Abstract transaction types, it will infer transaction type from typed
transaction byte or if decoding from RPC array from "type" field.

Transaction object can be created manually, or loaded from RPC, or created by binding. It also has some convenience
functions to fetch from RPC commonly fetched data from RPC, such as gas estimations or nonce. 

## Hash

Hash (and it's children classes, even Address) are basically prettier byte arrays of constant size. It has NULL() static
method that returns 0-filled Hash (or child object), comparison function `eq`, getter / setter combo (`fromBin`, 
`toBin`), and hex en/de-coding functions (`fromHex`, `toHex`).

All `fromHex` are tolerant for `0x` and for lack of `0x`, and `toHex` produces by default `0x`-prefixed strings, which
can be disabled with optional boolean parameter `bool $with0x` to false.

Children without modification other than byte array size:
 - BlockNonce
 - Bloom

Other children:
 - SolidityFunction4BytesSignature
 - Address

### Address

On top of standard things in Hash, `Address` performs [EIP-55](https://eips.ethereum.org/EIPS/eip-55) (mixed-case 
address checksum) validation on `fromHex()` function, and has additional method `checksummed()`, which should be always
preferred over `toHex()`. It also has `__toString()`, which allows casting to string. It just returns same 
as `checksummed()`.

## Block

`Block` is DTO class with helper function to instantiate from RPC response and see if it contains
[EIP1559](https://eips.ethereum.org/EIPS/eip-55) data (also known as post-london block). It contains all the standard
things the RPC returns using Ether Binder types. Fields are public, and meant to be accessed. 

## Receipt

`Receipt` is very similar to Block in the functionality sense - it's DTO class with fields like in RPC response receipt.
It has helper function to instantiate from RPC response and public fields to be accessed.

## Validator Withdrawal

`ValidatorWithdrawal` type is as previous two, a DTO for grabbing data from RPC. Again, it has utility function to parse
from RPC output array and typed public variables, meant to be accessed directly. 

## Solidity functions

2 Types that are tightly related with bindings - solidity can accept `function` type parameter which consists of
address + function signature. To allow calling such function in typed manner, appropriate type exists - `SolidityFunction`
with it's accompanied `SolidityFunction4BytesSignature` function which accepts signature as string or already processed
4 bytes. Since signature is just 4 bytes array, it's child class of Hash. 

## Transaction Type

`TransactionType` is enum representing type of transaction, it contains simple utility functions to get int or byte type
from [EIP-2718](https://eips.ethereum.org/EIPS/eip-2718) (Typed Transaction Envelope), spawn underlying transaction
(allowing to have common code for instantiating transaction of underlying type) and creating enum from various ways of
representing said EIP type number.
