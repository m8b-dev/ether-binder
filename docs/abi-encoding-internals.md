# ABI Encoding

Ether Binder internally implements ABI encoding, which is utilized in bindings.

There is a way to use it manually, although, this api is not targeted for simplicity of use, as rest of library, so bear
this in mind.

There are 2 ways to do this "manually". One way is to construct `M8B\EtherBinder\Contract\AbiTypes\*` tree manually,
another is to use existing `M8B\EtherBinder\Contract\ABIEncoder` driver class.

## Using driver class `ABIEncoder`

Internally, Ether Binder tries to operate on solidity function signatures, such as `transfer(uint256,address)`
(basically same stuff that is used for function selector in solidity via keccak256), and that's what's supported by
ABIEncoder driver class.

### Encoding

To encode to ABI, you need function signature (function name can be fictional, if you don't care about function selector)
```php
$signature = "foo(uint256,uint256)";
```
And array of data, using Ether Binder types:
```php
$data = [
    new \M8B\EtherBinder\Utils\OOGmp(10),
    new \M8B\EtherBinder\Utils\OOGmp(20)
];
```
Finally, you can call encode. If you want to also get encoding with [function selector](https://solidity-by-example.org/function-selector/)
(to use with eth call or as txn data)
```php
$selectorEnabled = true;
```
The last param is optional, and defaults to true.

Finally, you can get encoded binary blob. Note that's binary, so you may want to `bin2hex` it for presenting or throwing
into RPC.

```php
$bin = \M8B\EtherBinder\Contract\ABIEncoder::encode($signature, $data, $selectorEnabled);
```

To handle arrays, add them to signature, like you would do in function selector creation. There are known and unknown
length arrays, and they are internally parsed from the signature. In Data array, simply use nested array:
```php
$signature = "foo(uint256[])";
$data      = [
    [
        new \M8B\EtherBinder\Utils\OOGmp(123),
        new \M8B\EtherBinder\Utils\OOGmp(456),
        new \M8B\EtherBinder\Utils\OOGmp(789)
    ]
];
```

These arrays support nesting and known lengths. The usage should be intuitive, but bear in mind that in solidity, the
array structure is kinda backwards. Pay attention to amount of parameters on nest levels:
```php
$signature = "foo(uint256[][3])";
$data      = [
    [
        new \M8B\EtherBinder\Utils\OOGmp(123)
    ],
    [
        new \M8B\EtherBinder\Utils\OOGmp(456)
    ],
    [
        new \M8B\EtherBinder\Utils\OOGmp(789)
    ]
];
//sig[] [3]
$data[2][0]; // OK
$data[0][2]; // NOT OK
```

If you need to deal with solidity structs, these are called `tuples` on encoding side of things. For encoding, use them
as if they were arrays and for signature use `()` with inner data being types in solidity struct, order matters. If you
need to nest them, it's OK to have `()` inside another `()`. Notice that every time you create signature, like
`foo(uint256)`, it's actually tuple with single element of `uint256`, and array is on input. It's same thing but with
special treatment of having some arbitrary string (function name) at beginning.

Let's see complex example that explores cases of tuples. Of course, you can have array of tuples etc.

```php
$signature = "foo((uint256,(address)),(address,address),(address)[])";
$data      = [ // foo
    [ // (uint256,(address))
        new \M8B\EtherBinder\Utils\OOGmp(123),
        [ // (address)
            \M8B\EtherBinder\Common\Address::NULL()
        ]
    ],
    [ // (address,address)
        \M8B\EtherBinder\Common\Address::NULL(),
        \M8B\EtherBinder\Common\Address::NULL()
    ],
    [ // (address)[]
        [ // (address)
            \M8B\EtherBinder\Common\Address::NULL()
        ]
    ]
];
```

It's perfectly valid to have these structs as classes that implement [array access](https://www.php.net/manual/en/class.arrayaccess.php),
and actually that's what ABIGen does under the hood, that's why you can plug in ABIBinding's tuple objects, or why you
get these from encoder. 

### Decoding

Decoding is reversed encoding when using ABIEncoder. Define signature (with fictional function name), and pass in binary
data blob. Decoding can be done for function outputs or for events (with care for removing indexed elements from 
signature as they are not part of data blob of event).

The major difference is what you get back - you don't get the data itself back, but you get AbiTypes. To get actual data,
call `$output = $decodingResult->unwrapToPhpFriendlyVals($tuplerData)`. Tupler data is array that informs about types of
tuples. You can read more on it at the last section of this document. You can safely ignore it and supply null.
In case you opt to supply null, what's worth mentioning, is that in case of tuples, you will not get wraps into bound
solidity struct classes, it will simply be... an array.

Please read on Encoding to get idea how to construct signatures and what data to expect back. In case the binary blob
does not fit the declared signature, you might get exception, but you might simply get bogus data, so be aware.

## Manually constructing AbiTypes tree

Instead of using signatures, you can construct AbiTypes tree manually.

Externally, there are 2 kinds of elements of that tree:
- elements:
  - AbiAddress
  - AbiBool
  - AbiBytes
  - AbiFunction
  - AbiInt
  - AbiString
  - AbiUint
- containers:
  - AbiTuple
  - AbiArrayUnknownLength
  - AbiArrayKnownLength

Each of them is child of AbstractABIValue.

### Constructing the tree

Start on top level with **Tuple**. This is "root tuple".

Each **container** implements [array access](https://www.php.net/manual/en/class.arrayaccess.php), and this should be 
used for setting or getting data. Don't set data with constructor as purpose is different.

**Arrays**, both known and unknown size, take in constructor any instance of AbstractABIValue, that will be used as 
"template" object. So, for example, array `uint256[]` is `new AbiArrayUnknownLength(new AbiUint(null, 256))`.
For encoding, then fill in data using array access and providing instances of `AbstractABIValue` For decoding,
this is sufficient.

**Tuples** also need to have their types defined, for both encoding and decoding. Using array access, construct abi types,
like in array constructor, using 0-indexed position as key. For encoding, the constructed types need to contain their,
values and for decoding, empty types are sufficient.

Each **element** takes as first constructor argument nullable value. For encoding, it's where to supply data here. For 
decoding, it should be `null`. Data should have Ether Binder's types. See given constructor type. 

Some **element** abi types take additional constructor parameters:
 - int and uint take additional amount of bits, so for `uint256`, supply integer `256`.
 - bytes take optional size. Solidity type `bytes` should result in `0` value. Static solidity bytes types, such as 
   `bytes1` or `bytes32` take that size number from type, so for `bytes1` supply `1`, for `bytes2` supply `2`, etc...    

### Encoding

With tree prepared, call on root tuple:
```php
/** @var \M8B\EtherBinder\Contract\AbiTypes\AbiTuple $tree */
$binary = $tree->encodeBin();
```

### Decoding

With tree prepared, call on root tuple:
```php
/** @var \M8B\EtherBinder\Contract\AbiTypes\AbiTuple $tree */
$tree->decodeBin($dataArray, 0);
```
The second parameter MUST be 0. It's used internally for recursion.
Then, to get php friendly array, call

```php
$tupler = null;
$result = $tree->unwrapToPhpFriendlyVals($tupler);
```

The tupler array is optional, and can be null. To read more on it, see last section of this document.

### Debugging tree

When you have problems with constructed tree, each part of it can output debuggy string. Just take "root level" tuple,
and cast it to string. You should get output that represents the type. Note it's not signature. It may be filled in
with data, if it's set. Arrays are prefixed with `u` and `k` to differentiate between known size and unknown size arrays.

You can simply `echo` it for debugging purposes.

## Tupler Data

Relevant if you have tuples. It's an array that contains class-strings (string you can extract with ::class) in structure
that mirrors decoded data.
It's expected that class strings will point to class, that:
- take no required constructor parameters
- implement [array access](https://www.php.net/manual/en/class.arrayaccess.php)

The array cares about tuples and primitive types. Primitive types are nulls. Array of nulls with null tuple should 
be shortened to single null of parent.
Arrays are flattened (since array is not tuple)

```php
$signature = "foo((uint256,uint256,uint256,uint256,uint256)[][][][][][][],uint256[][][],uint256)"
$correctTupler = [ // "root level" 
    [ // (uint256,uint256,uint256,uint256,uint256)[][]...[]
    'tuple' => '\\Your\\Namespace\\TupleClass',
    'children' => [
            null,
            null,
            null,
            null,
            null
        ]
    ],
    null,
    null
];
```

What will happen, is that when tuple is found while decoding, and decoder is supplied with `"tuple"` item of array, the
class string will be used to spawn new instance, and array access used to plug in subsequent values with "normal" int
indexes, 0-indexed.
