# RLP Encoding

There is simple RLP encoder `M8B\EtherBinder\RLP\Encoder`. As everywhere in library you can opt to get binary blob or
hexadecimal representation with these 2 methods:
```php
$hex = M8B\EtherBinder\RLP\Encoder::encodeHex(["data"]);
$bin = M8B\EtherBinder\RLP\Encoder::encodeBin(["data"]);
```

Encoding has a catch, that you might not expect - it does not wrap data into array. Most RLP encoders will wrap input
into array, in example above, you will get "raw" encoding of `"data"` string, not `["data"]`. This allows you to encode
for example typed transactions with params `[$transactionType, [$transactionData1, $transactionData2, ...]]`, such that
first byte will be `$transactionType`, not array declaration.

# RLP Decoding

Similar to encoding, there is decoding, which is consistent with encoding. You can use it with:

```php
$data = M8B\EtherBinder\RLP\Decoder::decodeRLPBin($binaryBlob);
$data = M8B\EtherBinder\RLP\Decoder::decodeRLPHex($hexadecimal);
```

And again, if you pass RLP that's array from get-go, like LegacyTransaction, you will get nested array `[[txData]]`,
while if you pass Typed Transaction RLP you will get `[transactionType, [txData]]`.
