# CHANGELOG

## 2.2.0 - 2015-05-27

* Added support for [JEP-12](https://github.com/jmespath/jmespath.site/blob/master/docs/proposals/raw-string-literals.rst)
  and raw string literals (e.g., `'foo'`).

## 2.1.0 - 2014-01-13

* Added `JmesPath\Env::cleanCompileDir()` to delete any previously compiled
  JMESPath expressions.

## 2.0.0 - 2014-01-11

* Moving to a flattened namespace structure.
* Runtimes are now only PHP callables.
* Fixed an error in the way empty JSON literals are parsed so that they now
  return an empty string to match the Python and JavaScript implementations.
* Removed functions from runtimes. Instead there is now a function dispatcher
  class, FnDispatcher, that provides function implementations behind a single
  dispatch function.
* Removed ExprNode in lieu of just using a PHP callable with bound variables.
* Removed debug methods from runtimes and instead into a new Debugger class.
* Heavily cleaned up function argument validation.
* Slice syntax is now properly validated (i.e., colons are followed by the
  appropriate value).
* Lots of code cleanup and performance improvements.
* Added a convenient `JmesPath\search()` function.
* **IMPORTANT**: Relocating the project to https://github.com/jmespath/jmespath.php

## 1.1.1 - 2014-10-08

* Added support for using ArrayAccess and Countable as arrays and objects.

## 1.1.0 - 2014-08-06

* Added the ability to search data returned from json_decode() where JSON
  objects are returned as stdClass objects.
