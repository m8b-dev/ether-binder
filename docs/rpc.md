# JSON RPC client

Ether Binder comes with HTTP JSON RPC client. It's used with execution client to interact with blockchain. It facilitates
such operations as reading blocks, fetching and sending transactions and such.

# Instantiation

Right now only http\[s\] RPC is supported. To instantiate it, just pass url. For some RPC providers you may need custom
headers for example for authorization. Only required parameter is url. Note that `Content-Type` header will always be set
to `application/json`, even its set in param to be something else

```php
$rpc = new \M8B\EtherBinder\RPC\HttpRPC("https://example.com", ["X-Api-Key" => "foo-bar"])
```

# Usage

## Naming

RPC follows naming convention of following RPC function names, removing `_` to split function name. For example 
`eth_getBlockByHash` becomes `ethGetBlockByHash`.

## Block number param

Some endpoints require block number parameter, which is either
string or block number on RPC. In Ether-Binder the parameter becomes int or enum (`BlockParam`). Available `BlockParam`s:
```php
BlockParam::LATEST
BlockParam::EARLIEST
BlockParam::PENDING
BlockParam::SAFE
BlockParam::FINALIZED
```
Names of these are consistent with RPC.

## Endpoint specific reference

There is no documentation for specific RPC points in Ether-Binder library, as you will get the best with official JSON RPC
of either your specific provider, or your specific execution client [like this for geth](https://geth.ethereum.org/docs/interacting-with-geth/rpc),
or you can always see [this official docs](https://ethereum.org/en/developers/docs/apis/json-rpc/) or [this official spec](https://ethereum.github.io/execution-apis/api-documentation/).
Since there is plenty of documentation for these, it would be redundant and counter-productive to rewrite this.

If you take RPC as parameter, you **SHOULD** type for `AbstractRPC`, not `HttpRPC`. This allows in future implementation
of different transports

## Missing methods

Some clients implement additional functions which differ from the official spec. Most often used function that can be
an example is `debug_traceTransaction`. While very common method, it's not standard as per spec. To call non-standard
method, use `runRpc` method.

```php
$rpc->runRpc("module_someMethod", ["param1", "param2"]);
```

Note that this function requires strings as inputs. Most of Ether Binder types support functions that should help you,
such as `toHex()` and `encodeHex()`. Output is always array, as this function is unaware if return type is single value
or not. For single returns, it might be most convenient to use `::fromHex()` static functions. 

# Adding more supported protocols

Create new class, that extends `AbstractRPC` and implement `__construct` to your liking. Next, you need to implement one
function to make it functional:
```php
public function raw(string $method, ?array $params = null): array
```

It's your responsibility to track `id` of the request.

You must ensure only exception that will be thrown is `M8B\EtherBinder\Exceptions\RPCGeneralException`. 

You put zero effort beyond `json_decode` into parsing the output, the AbstractRPC will take care of this. Note 2 things:
- you should use JSON_THROW_ON_ERROR to be able to catch it and throw RPCGeneralException
- you must decode with assoc param set to true 

If `$params` is null, set it to empty array - `[]`

Param `$method` should be passed "as-is" to the RPC, same with params.

Return output array "as is".

# Internal structure

Specific method bindings are split into modules:
```
AbstractModule
Debug
Eth
Net
Web3
```

Each one extends previous one, in alphabetical order, AbstractModule MUST be parent of all of them. It defines abstract
function that is implemented by AbstractRPC to actually call the RPC, and AbstractRPC is child of last Compound, and 
it's parent of AbstractRPC. This approach allows nice division for modules. Compound is special class that contains
non-existent RPCs that may be useful. Right now it only provides `calcAvgTip` and `isLookingLikeLondon` helper functions
required for internal creation of transactions (one decides if we need to create London Transaction or Legacy one, another
one helps to decide what's average miner Tip value, to use that as default tip)

There are some helpers for making api more convenient.
Eth module contains helper that parses transaction into message (since this library avoids Message).
AbstractModule contains helper to allow accepting Block or Hash for block hash, and to parse BlockParam into RPC value. 
