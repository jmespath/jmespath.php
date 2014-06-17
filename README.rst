============
jmespath.php
============

JMESPath (pronounced "jaymz path") allows you to declaratively specify how to
extract elements from a JSON document.

*jmespath.php* allows you to use JMESPath in PHP applications with PHP
data structures. It requires PHP 5.4+ and can be installed through
`Composer <http://getcomposer.org/doc/00-intro.md>`_ using
``mtdowling/jmespath.php``.

.. code-block:: php

    require 'vendor/autoload.php';

    $expression = 'foo.*.baz';

    $data = [
        'foo' => [
            'bar' => ['baz' => 1],
            'bam' => ['baz' => 2],
            'boo' => ['baz' => 3]
        ]
    ];

    \JmesPath\Env::search($expression, $data);
    // Returns: [1, 2, 3]

- `JMESPath documentation <http://jmespath.readthedocs.org/en/latest/>`_
- `JMESPath Grammar <http://jmespath.readthedocs.org/en/latest/specification.html#grammar>`_
- `JMESPath Python library <https://github.com/boto/jmespath>`_

Installing
==========

jmespath.php requires PHP 5.4 or greater. While it may be possible to install
jmespath.php in various various ways, the only supported method of
installing JMESPath is through Composer.

Update your project's composer.json (in the root directory of your project):

   .. code-block:: js

      {
          "require": {
              "mtdowling/jmespath.php": "<2"
          }
      }

Then install your dependencies using ``./composer.phar install``.

PHP Usage
=========

The ``JmesPath\Env::search`` function can be used in most cases when using the
library. This function utilizes a JMESPath runtime based on your environment.
The runtime utilized can be configured using environment variables and may at
some point in the future automatically utilize a C extension if available.

.. code-block:: php

    $result = JmesPath\Env::search($expression, $data);

Runtimes
--------

jmespath.php utilizes *runtimes*. There are currently two runtimes:
DefaultRuntime and CompilerRuntime.

DefaultRuntime is utilized by ``JmesPath\Env::search()`` by default. Depending on
your application, it may be useful to customize the runtime used by
``JmesPath\Env::search()``. You can change the runtime utilized by
``JmesPath\Env::search()`` by calling ``JmesPath\registerRuntime()``, passing in an
instance of ``JmesPath\Runtime\RuntimeInterface``.

DefaultRuntime
~~~~~~~~~~~~~~

The DefaultRuntime will parse an expression, cache the resulting AST in memory,
and interpret the AST using an external tree visitor. DefaultRuntime provides a
good general approach for interpreting JMESPath expressions that have a low to
moderate level of reuse.

CompilerRuntime
~~~~~~~~~~~~~~~

``JmesPath\Runtime\CompilerRuntime`` provides the most performance for
applications that have a moderate to high level of reuse of JMESPath
expressions. The CompilerRuntime will walk a JMESPath AST and emit PHP source
code, resulting in anywhere from 7x to 60x speed improvements.

Compiling JMESPath expressions to source code is a slower process than just
walking and interpreting a JMESPath AST (via the DefaultRuntime). However,
running the compiled JMESPath code results in much better performance than
walking an AST. This essentially means that there is a warm-up period when
using the ``CompilerRuntime``, but after the warm-up period, it will provide
much better performance.

Use the CompilerRuntime if you know that you will be executing JMESPath
expressions more than once or if you can pre-compile JMESPath expressions
before executing them (for example, server-side applications).

Environment Variable
^^^^^^^^^^^^^^^^^^^^

You can utilize the CompilerRuntime in ``JmesPath\Env::search()`` by setting
the ``JP_PHP_COMPILE`` environment variable to "on" to or to a directory
on disk used to store cached expressions.

Testing
=======

A comprehensive list of test cases can be found at
https://github.com/mtdowling/jmespath.php/tree/master/tests/JmesPath/compliance.
These compliance tests are utilized by jmespath.php to ensure consistency with
other implementations, and can serve as examples of the language.

jmespath.php is tested using PHPUnit. In order to run the tests, you need to
first install the dependencies using Composer as described in the *Installation*
section. Next you just need to run the tests via make:

.. code-block:: bash

    make test

You can run a suite of performance tests as well:

.. code-block:: bash

    make perf
