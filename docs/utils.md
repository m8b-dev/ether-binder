# Utilities

Ether Binder comes with some utilities:

## WeiFormatter

Helper to parse Ether units from and to human format. It allows some common human notations, can survive white spaces,
it supports `,` and `.`, and even works with `10,000.00` human notation. It can work with floats too.
From human static function returns `OOGmp` of parsed value and from wei returns string.

`WeiFormatter::fromHuman("10,123.45)` will return `OOGmp` with value of `10123.45 * 10**18`.

It also accepts OOGmp to allow converting with common logic across Ethereum notation formats, like GWEI to ETHER.

To leverage this in such way, there is special enum that defines supported formats - `EtherFormats`. This parameter is
last in both functions, and always refers to "human" side of input or output.

`fromWei` requires second parameter which is how many decimal places should be taken.

Example usage:

```php
// convert 12 gwei to eth
$twelveGwei = \M8B\EtherBinder\Utils\WeiFormatter::fromHuman(12, \M8B\EtherBinder\Utils\EtherFormats::GWEI);
$twelveGweiInEther = \M8B\EtherBinder\Utils\WeiFormatter::fromWei($twelveGwei, 10);
echo $twelveGweiInEther; // 0.0000000120

$valueEth = \M8B\EtherBinder\Utils\WeiFormatter::fromHuman("10");
echo $valueEth->toString(); // 10000000000000000000
```

## OOGmp

Object wrapper for php's standard `gmp` library. This object is used practically everywhere in library where big numbers
come into play. The constructor can take strings, integers or GMP itself. In case of creating with string, you SHOULD pass
hex string with `0x` prefix OR supply second parameter, base as 16. If that's not done, the hex string may look like
"normal" decimal string, and the OOGmp will do best-effort to guess, which will result in parsing as decimal.

Consider example:
```php
print_r([
    "0x20" => (new \M8B\EtherBinder\Utils\OOGmp("0x20"))->toString() 
    "20" => (new \M8B\EtherBinder\Utils\OOGmp("20"))->toString()
    "20,16" => (new \M8B\EtherBinder\Utils\OOGmp("20", 16))->toString()
]);
```
will return:
```
Array
(
    [0x20] => 32
    [20] => 20
    [20,16] => 32
)
```

Alternatively, instead of constructor you may use `::wrap()` to wrap existing GMP. Note that you can just do that with
constructor.

### Arithmetic Functions:

The class provides basic arithmetic operations like addition (`add`), subtraction (`sub`), multiplication (`mul`), and division (`div`).
These methods support automatic type normalization, allowing you to pass in `OOGmp|int|GMP` as arguments.
None of these functions modify state of object it's called for, and instead return new `OOGmp` instance. Argument is
always "right" side of equation, while object it's called on is always "left" side of equation. It's OK, and even
encouraged to chain operations, and even when needed finish it off with some form of encoding:

```php
$value = new \M8B\EtherBinder\Utils\OOGmp(10);
echo $value
    ->mul(10) // 100
    ->add(20) // 120
    ->div(6)  // 20
    ->mod(3)  // 3
    ->toString(); // "3"
```

### Comparison Functions:

Standard comparison operations are included, such as `eq` (equals), `lt` (less than), `gt` (greater than), etc.
Aliases are also available, like `eq` - `equal`. These functions also support automatic type normalization, just like
arithmetic functions.

### Getting data

OOGmp allows you to fetch internal data in few ways.

#### raw

You can get raw underlying gmp
```php
$value = new \M8B\EtherBinder\Utils\OOGmp(10);
$value->raw(); // returns \GMP
```

#### toString

You can get string representation (also has `__toString`):
```php
$value = new \M8B\EtherBinder\Utils\OOGmp(10);
$string = $value->toString();
```
This method has couple of options:
```
0: bool $hex = false
    if true, returns hexadecimal string, instead of decimal string
1: bool $no0xHex = false
    param is ignored if $hex is false, if set to true, the hex string will be without 0x prefix
2: ?int lPad0 = null
    if null, logic is ignored. Integer desired length of string, which will be achieved by padding from left with zeroes.
    Note that if resulting string without padding is longer than the param, the string will not be truncated, but will be
    returned as if the param was null.
```

For negative numbers, you get two's complement format, like -256 = "0xFF00".

#### toBin

Encodes the integer in binary format. Most likely you want to supply optional parameter to get known length of that int.

```php
$lPad0 = 32;
$value = new \M8B\EtherBinder\Utils\OOGmp(10);
$binary = $value->toBin($lPad0);
```

Left Pad 0 takes amount of BYTES of desired length, so hexadecimal representation of this binary blob will be 64 
characters long (without 0x). It is optional parameter.
For negative numbers, you get two's complement format, just like with hex string.


## Functions

is class that is host for misc utility functions.

### assert hex size

`mustHexLen` validates if string is hexadecimal of given length, and throws InvalidHexLengthException if length is wrong,
or InvalidHexException if the string isn't valid hex. Bear in mind, that length does NOT include 0x prefix, which is
optional
```php
$hex = "0f00ba12"
$len = 8;
\M8B\EtherBinder\Utils\Functions::mustHexLen($hex, $len)
```

### left pad hex

`lPadHex` Left-pads a hex string to a specific length. It supports multiplies of padding, for example if you need to
pad unknown length string to be multiples of 2 for proper byte representation. That's what last param is enabling.
First param is hex to be padded, and second param is the pad length 
```php
\M8B\EtherBinder\Utils\Functions::lPadHex("f00", 2, true); // 0f00
```

### int <-> hex string

`int2hex` is basically proxy function to `dechex` but with "0x" support, and `hex2int` is opposite, but using OOGmp for
exception support like exceeding max int size and 0x prefix tolerance.
```php
\M8B\EtherBinder\Utils\Functions::hex2int("0xff"); // 255
\M8B\EtherBinder\Utils\Functions::int2hex(255); // 0xff
```

### next block fee

`getNextBlockBaseFee` deterministically calculates next block's base fee. If you want to emulate the behaviour, to 
account for example next N blocks worst case scenario, these values of block you will need to fill in:
```php
$block = new \M8B\EtherBinder\Common\Block();
$block->baseFeePerGas = $previousFunctionCallOutput;
$block->number = $previousBlock->number + 1;
$block->gasLimit = $previousBlock->gasLimit;
$block->gasUsed = $previousBlock->gasLimit;
```

The second parameter is internal type EIP1559Config, which as of now, simply defines some constants and concrete chains
only inform about eip 1559 activation block. If your chain isn't on the static functions list, just use `sepolia`, if you
are sure that you always call on EIP 1559 enabled chain. 

Usage:
```php
$block = $rpc->ethGetBlockByNumber();
$nextBlockFee = \M8B\EtherBinder\Utils\Functions::getNextBlockBaseFee($block, \M8B\EtherBinder\Misc\EIP1559Config::mainnet());
```

### Blocking wait for transaction

This function will wait and block until the transaction is confirmed via repetitively checking receipt and catching errors
until timeout runs out. It accepts either Transaction or Hash objects, and will wait up to 3rd param of seconds and query
rpc (2nd param) every 4th param of milliseconds. 

```php
$rpc->ethSendRawTransaction($transaction);
$receipt = \M8B\EtherBinder\Utils\Functions::waitForTxReceipt(
    $transaction, $rpc, 300 /*5 minutes*/, 1000 /* check every second*/);
```
