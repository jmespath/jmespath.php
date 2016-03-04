============
jmespath.php
============

JMESPath (pronounced "jaymz path") allows you to declaratively specify how to
extract elements from a JSON document. *jmespath.php* allows you to use
JMESPath in PHP applications with PHP data structures. It requires PHP 5.4 or
greater and can be installed through `Composer <http://getcomposer.org/doc/00-intro.md>`_
using the ``mtdowling/jmespath.php`` package.

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

    JmesPath\search($expression, $data);
    // Returns: [1, 2, 3]

- `JMESPath Tutorial <http://jmespath.org/tutorial.html>`_
- `JMESPath Grammar <http://jmespath.org/specification.html#grammar>`_
- `JMESPath Python library <https://github.com/jmespath/jmespath.py>`_

PHP Usage
=========

The ``JmesPath\search`` function can be used in most cases when using the
library. This function utilizes a JMESPath runtime based on your environment.
The runtime utilized can be configured using environment variables and may at
some point in the future automatically utilize a C extension if available.

.. code-block:: php

    $result = JmesPath\search($expression, $data);

    // or, if you require PSR-4 compliance.
    $result = JmesPath\Env::search($expression, $data);

Runtimes
--------

jmespath.php utilizes *runtimes*. There are currently two runtimes:
AstRuntime and CompilerRuntime.

AstRuntime is utilized by ``JmesPath\search()`` and ``JmesPath\Env::search()``
by default.

AstRuntime
~~~~~~~~~~

The AstRuntime will parse an expression, cache the resulting AST in memory,
and interpret the AST using an external tree visitor. AstRuntime provides a
good general approach for interpreting JMESPath expressions that have a low to
moderate level of reuse.

.. code-block:: php

    $runtime = new JmesPath\AstRuntime();
    $runtime('foo.bar', ['foo' => ['bar' => 'baz']]);
    // > 'baz'

CompilerRuntime
~~~~~~~~~~~~~~~

``JmesPath\CompilerRuntime`` provides the most performance for
applications that have a moderate to high level of reuse of JMESPath
expressions. The CompilerRuntime will walk a JMESPath AST and emit PHP source
code, resulting in anywhere from 7x to 60x speed improvements.

Compiling JMESPath expressions to source code is a slower process than just
walking and interpreting a JMESPath AST (via the AstRuntime). However,
running the compiled JMESPath code results in much better performance than
walking an AST. This essentially means that there is a warm-up period when
using the ``CompilerRuntime``, but after the warm-up period, it will provide
much better performance.

Use the CompilerRuntime if you know that you will be executing JMESPath
expressions more than once or if you can pre-compile JMESPath expressions
before executing them (for example, server-side applications).

.. code-block:: php

    // Note: The cache directory argument is optional.
    $runtime = new JmesPath\CompilerRuntime('/path/to/compile/folder');
    $runtime('foo.bar', ['foo' => ['bar' => 'baz']]);
    // > 'baz'

Environment Variables
^^^^^^^^^^^^^^^^^^^^^

You can utilize the CompilerRuntime in ``JmesPath\search()`` by setting
the ``JP_PHP_COMPILE`` environment variable to "on" or to a directory
on disk used to store cached expressions.

Custom functions
----------------

The JMESPath language has numerous
`built-in functions
<http://jmespath.org/specification.html#built-in-functions>`__, but it is
also possible to add your own custom functions.  Keep in mind that
custom function support in jmespath.php is experimental and the API may
change based on feedback.

**If you have a custom function that you've found useful, consider submitting
it to jmespath.site and propose that it be added to the JMESPath language.**
You can submit proposals
`here <https://github.com/jmespath/jmespath.site/issues>`__.

To create custom functions:

* Create any `callable <http://php.net/manual/en/language.types.callable.php>`_
  structure (loose function or class with functions) that implement your logic.
* Call ``FnDispatcher::getInstance()->registerCustomFn()`` to register your function.
  Be aware that there ``registerCustomFn`` calls must be in a global place where they
  always will be executed.

Here is an example with a class instance:

.. code-block:: php

    // Create a class that contains your function
    class CustomFunctionHandler
    {
        public function double($args)
        {
            return $args[0] * 2;
        }
    }
    FnDispatcher::getInstance()->registerCustomFn('myFunction', [new CustomFunctionHandler(), 'double'])

An example with a runtime function:

.. code-block:: php

    $callbackFunction = function ($args) {
        return $args[0];
    };
    $fn->registerCustomFn('myFunction', $callbackFunction);

As you can see, you can use all the possible ``callable`` structures as defined in the PHP documentation.
All those examples will lead to a function ``myFunction()`` that can be used in your expressions.

Type specification
^^^^^^^^^^^^^^^^^^

The ``FnDispatcher::getInstance()->registerCustomFn()`` function accepts an
optional third parameter that allows you to pass an array of type specifications
for your custom function. If you pass this, the types (and count) of the passed
parameters in the expression will be validated before your ``callable`` is executed.

Example:

.. code-block:: php
    $fn->registerCustomFn('myFunction', $callbackFunction, [['number'], ['string']]);

Defines that your function expects exactly 2 parameters, the first being a ``number`` and
the second being a ``string``. If anything else is passed in the call to your function,
a ``\RuntimeException`` will be thrown.

Testing
=======

A comprehensive list of test cases can be found at
https://github.com/jmespath/jmespath.php/tree/master/tests/compliance.
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
