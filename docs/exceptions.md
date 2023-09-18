# Exceptions

Ether Binder relies on exceptions. Aim is that all exceptions thrown by library are children of
`\M8B\EtherBinder\Exceptions\EtherBinderException`. Then there are 3 other "basic" exceptions, which are used as their
"vanilla php" variant:
```
EthBinderArgumentException
EthBinderLogicException
EthBinderRuntimeException
```

If EthBinderLogicException is thrown, this should mean bug in library. Exceptions try to explain the problem in their
name, and functions should have documentation block with thrown exceptions, so you can leverage your IDE to either see
at glance what's thrown or even auto-generate catch clauses. In case you don't care about specific exception, you can
just use the "main" `EtherBinderException`, and that's the express purpose of this exception.
