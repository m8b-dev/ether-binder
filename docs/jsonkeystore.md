# A note on JSON keystore

JSON keystore is dropped due to fact it requires scrypt. Scrypt kdf in pure php is extremly slow, and there are 2 solutions:
- supplying C build for it, and using FFI. This approach has flaw that different php enviroments have different CPU extensions,
  and running this without building for cpu extensions is going to be very slow too. Also that requires a cross-compile for all
  php distributions and having all the blobs in the repo. Just not feasable.
- pecl package - that could solve that, but would mean library is unusable without pecl (composer require) or throwing an
  unexpected exception when trying to open JSON keystore, which may be hidden for dev if dev have it installed, and
  frustrated when it crashes on actual environment. Reasonable alternative would be addon to this library to handle
  this and require a [pecl extension](https://pecl.php.net/package/scrypt) in composer file.
