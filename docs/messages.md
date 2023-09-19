# Messages

Ether Binder has simple utility to sign message, with support for few formats.
Unfortunately, the signed message is not normalized and seemingly every second implementation of it defines its own.
Most of the implementations if not all of them, in the end boil down to taking the message, processing it, hashing it
and signing that hash. The processing usually involves adding `\x19Ethereum Signed Message:` prefix.

## Message formats

Right now there are 3 formats supported:
- Geth-like: `message = \x19Ethereum Signed Message:\n{len(message)}{message}`
- Hashed: `message = \x19Ethereum Signed Message:\n32{keccak256(message)}`
- Unformatted: `message = message`

You can also define your own message type by extending `AbstractSigningMessage` and implementing single method:
```php
protected function preProcessMessage(): string
{
    return $this->message; // Unformatted implementation
}
```

## Usage

Signed messages support bidirectional JSON en/de-coding (note that re-encoding with Ether Binder will lose original 
signer and version). Usage on can be also fully done on Ether Binder's types.

### Signing

To sign message, select format by choosing implementation (like GethLike in example below), supply the message to
constructor, and use `sign()` with private key.

```php
$msg = new \M8B\EtherBinder\Misc\GethLikeMessage("hello, world!");
$msg->sign($key);
$sigObj = $msg->getSignature();
echo $msg->toJson(true);
```

### Validating

To validate message, it needs to get instantiated, and then `validateSignature()` method can be called. It returns bool
if message is matching declared address.

#### From JSON

One way of instantiating the message object is using `::fromJSON` static method:

```php
$json = <<<HEREDOC
{
    "address": "0x01004e8872e338A16C55A670e652807EA1C6D94f",
    "msg": "0x48656c6c6f2c20776f726c6421",
    "sig": "fe177049e12da2dff5279a2388b50bf407440ca804d9bcb21ab6a61a553089484c54bde8cfd091a6a4f11e59085e486d5dc7b05aa11a7e3d1884c8501406b4fc1c",
    "version": "1",
    "signer": "eth-binder"
}
HEREDOC;
;
$msg = \M8B\EtherBinder\Misc\GethLikeMessage::fromJSON($json);
$isOK = $msg->validateSignature();

echo $isOK ? "signature matched" : "invalid signature";
```

#### From already parsed data

Alternatively, Ether Binder types can be utilized 

```php
$msgString = "Hello, world!";
$from      = \M8B\EtherBinder\Common\Address::fromHex("0x01004e8872e338A16C55A670e652807EA1C6D94f");
$signature = \M8B\EtherBinder\Crypto\Signature::fromHex("0xfe177049e12da2dff5279a2388b50bf407440ca804d9bcb21ab6a61a553089484c54bde8cfd091a6a4f11e59085e486d5dc7b05aa11a7e3d1884c8501406b4fc1c");
$msg       = new \M8B\EtherBinder\Misc\GethLikeMessage($msgString, $from, $signature);
$isOK      = $msg->validateSignature();

echo $isOK ? "signature matched" : "invalid signature";
```
