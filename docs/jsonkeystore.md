# A note on JSON keystore

JSON keystore is available as separate library to ether binder. That's because it requires `scrypt` pecl extension, and
it's not reasonable to expect this extension to be available in most environments. So, explicitly it's possible to import
the package as separate dependency.

See:
 - [Packagist](https://packagist.org/packages/m8b/ethbnd-keystore)
 - [Github](https://github.com/m8b-dev/ether-binder-json-keystore)
