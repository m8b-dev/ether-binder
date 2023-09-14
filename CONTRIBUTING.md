# To contribute create a PR.

Standard GitHub contributing flow. Fork -> commit -> create PR.
Don't PR to `master`, PR to `dev` branch.

### Code style

Try to stick to code style:
- this repo uses tabs not spaces for indents, one per level. Tab width = 4 spaces.
  Additional indentation with spaces is OK.
- double quotes (`"`) are prefered over (`'`)
- concat operator doesn't need whitespace, but it's ok if it has one in more complex expressions
- there is no white space between flow control and `(`, for example `if(`, `for(` etc.
- when having multiples of same / similar operations one under another,
  make them aligned. Exmaples: 
  ```php
  <?php
  $foo         = $arr["foo"];
  $fooBar      = $arr["fooBar"];
  $fooBarBaz   = $arr["fooBarBaz"];
  $arrayAssign = [
    "key one"   => 1,
    "key two"   => 2,
    "key three" => 3,
  ]; 
    
  if(
       ($someComplexCondition)
    && ($some || $other || $complex || $condition)
    && ($etc)
  )
  ```
- no closing `?>` tag, ever. Full `<?php` tag.
- Classes us `PascalCase`
- Functions and methods use `camelCase`
- Constants may use `camelCase` if not public (private / protected), otherwise `CAPSLOCK_CASE`
- Enum values use `CAPSLOCK_CASE`
- Minor code-golfing is allowed, but must be limited.
- method definition (ie. `function foo(): returnT)`) and opening `{` must be separated by new line
- trivial methods can have one-liner `{ operation }`, ie.
  ```php
  public function getFoo(): FooType
  { return $this->foo; }
  ```
  but full-size form is OK too:
  ```php
  public function getFoo(): FooType
  {
    return $this->foo;
  }
  ```
- Where applies use `?` and `??` operators instead of `if`, unless that would significantly decease readability
- No `expression OR expression`, like `$foo or fooIsFalse();`  
- Line limit is soft, but avoid exceeding 120 characters per line. \t counts as 4 characters.

### Other codebase rules

- In general `static` is preferred compared to `self`. This enables easier inheritance and extending classes, for library users too.
- JSON decoding is always associative (`json_decode($json, true)`)
- Internally data should be bytes not hexes wherever possible. Hexes are OK for representation purposes, but underlying
  data should always be stored and processed on byte arrays in form of `string`.
- Try to minimize amount of PHP extensions used. For core library hard no-go for any non-standard extension like `crypt`
- Declare extensions if used in composer.json file. Core extensions (like json, curl) are OK, unless can be trivially avoided.
- Including composer library must be well justified. For example "don't do your own cryptography". Encodings and such are
  ought to be implemented inside the library itself, to make it as independent as possible. Including alternative
  implementations of similar libraries is not allowed since this library aims for fostering independent implementations.
  This would be like implementing OpenEthereum \[\*\] with bindings from Geth for let's say EVM code.
- Many parts could be replaced with regex. But regex is avoided for arbitrary reasons 

### Boilerplate

Boilerplate to new files after `<?php` tag:

```

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

```

If some .php file misses boilerplate, please create an issue. Thank you.
