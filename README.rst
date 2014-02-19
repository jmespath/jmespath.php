============
jmespath.php
============

JMESPath (pronounced "jaymz path") allows you to declaratively specify how to
extract elements from a JSON document.

*jmespath.php* allows you to use JMESPath in PHP applications with PHP
data structures. It requires PHP 5.3+ and can be installed through
`Composer <http://getcomposer.org/doc/00-intro.md>`_ using
``mtdowling/jmespath.php``.

.. code-block:: php

    require 'vendor/autoload.php';

    $expression = 'foo.*.baz';

    $data = [
        'foo': [
            'bar' => ['baz' => 1],
            'bam' => ['baz' => 2],
            'boo' => ['baz' => 3]
        ]
    ];

    JmesPath\search($expression, $data);

    // Returns: [1, 2, 3]

- `JMESPath documentation <http://jmespath.readthedocs.org/en/latest/>`_
- `JMESPath Grammar <http://jmespath.readthedocs.org/en/latest/specification.html#grammar>`_
- `JMESPath Python libary <https://github.com/boto/jmespath>`_

Installing
==========

jmespath.php requires PHP 5.3 or greater.

1. Download and install Composer: https://getcomposer.org/doc/00-intro.md#installation-nix

   .. code-block:: bash

      curl -sS https://getcomposer.org/installer | php
      ./composer.phar install

2. Update your project's composer.json (in the root directory of your project):

   .. code-block:: js

      {
          "require": {
              "mtdowling/jmespath.php": "<2"
          }
      }

3. Install dependencies using ``./composer.phar install``

PHP Usage
=========

After installing through Composer, jmespath.php will autoload a
``functions.php`` file that contains a ``JmesPath\search`` function. This
function can be used in most cases when using the library.

.. code-block:: php

    $result = JmesPath\search($expression, $data);

.. note::

    If you do not install through Composer, then you will need to manually
    require the functions.php script.

Runtimes
--------

jmespath.php utilizes *runtimes*. There are currently two runtimes:
DefaultRuntime and CompilerRuntime.

DefaultRuntime is utilized by ``JmesPath\search()`` by default. Depending on
your application, it may be useful to customize the runtime used by
``JmesPath\search()``. You can change the runtime utilized by
``JmesPath\search()`` by calling ``JmesPath\registerRuntime()``, passing in an
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
before executing them. This runtime works really well for server-side
applications that can incur a warm-up penalty or applications that

Customizing the runtime
~~~~~~~~~~~~~~~~~~~~~~~

You can create runtimes using the ``JmesPath\createRuntime`` factory method.
This method accepts an associative array of parameters, including ``parser``
which can be used to change the Parser used by a runtime, ``interpreter``
which can be changed to use a custom external tree visitor used to interpret
expressions, and ``compile`` which can be used to determine if JMESPath
expressions will be compiled. Set ``compile`` to a directory to store compiled
PHP source code in a specific directory, or to ``true`` to compile JMESPath
expressions to your system's temporary directory.

The following example shows how to register a CompilerRuntime with
``JmesPath\search()``:

.. code-block:: php

    $runtime = JmesPath\createRuntime(array(
        'compile' => '/path/to/compile_directory'
    ));

    JmesPath\registerRuntime($runtime);

Testing
=======

A comprehensive list of test cases can be found at
https://github.com/mtdowling/jmespath.php/tree/master/tests/JmesPath/compliance.
These compliance tests are utilized by jmespath.php to ensure consistency with
other implementations, and can serve as examples of the language.

jmespath.php is tested using PHPUnit. In order to run the tests, you need to
first install the dependencies using Composer as described in the *Installation*
section. Next you just need to run the tests using phpunit:

.. code-block:: bash

    vendor/bin/phpunit
