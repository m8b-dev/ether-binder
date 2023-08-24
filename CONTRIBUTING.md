# To contribute create a PR.
Standard github contributing flow.

### Code style

Try to stick to code style:
- this repo uses tabs not spaces for indents, one per level. 
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
- no closing `?>` tag, full `<?php` tag.
- Classes us `PascalCase`
- Functions and methods use `camelCase`
- Constants may use `camelCase` if not public (private / protected), otherwise `CAPSLOCK_CASE`
- Enum values use `CAPSLOCK_CASE`

### Boilerplate

Try to add boilerplate to new files after `<?php` tag:

```

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

```
