============
jmespath.php
============

JMESPath (pronounced "jaymz path") allows you to declaratively specify how to
extract elements from a JSON document.

*jmespath.php* allows you to use JMESPath in PHP applications with PHP arrays.
It requires PHP 5.3+ and can be installed through
`Composer <http://getcomposer.org/doc/00-intro.md>`_.

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

    // > [1, 2, 3]

A comprehensive list of test cases can be found at https://github.com/boto/jmespath/tree/develop/tests/compliance.
These compliance tests are utilized by jmespath.php to ensure consistency with
other implementations, and can serve as examples of the language.

- `JMESPath documentation <http://jmespath.readthedocs.org/en/latest/>`_
- `JMESPath Grammar <http://jmespath.readthedocs.org/en/latest/specification.html#grammar>`_
- `JMESPath Python libary <https://github.com/boto/jmespath>`_

PHP Usage
=========

After installing through Composer, jmespath.php will autoload a convenient
``search`` function in the ``JmesPath`` namespace: ``JmesPath\search()``. This
function should be used for almost every case when using the library.

.. code-block:: php

    require 'vendor/autoload.php';

    $result = JmesPath\search($expression, $data);

You are, of course, free to use the underlying implementation directly if
needed. This could be useful to add an APC driven caching layer to cache
bytecode for a given expression across requests.

.. code-block:: php

    require 'vendor/autoload.php';

    $lexer = new JmesPath\Lexer();
    $parser = new JmesPath\Parser($lexer);
    $interpreter = new JmesPath\Interpreter();

    $opcodes = $parser->compile('foo.bar.{"baz": baz, bar: sub.node}');
    $result = $interpreter->execute($opcodes, $data);

Testing
=======

jmespath.php is tested using PHPUnit. In order to run the tests, you need to
first install the dependencies using Composer:

    composer.phar install

Now you just need to run the tests using phpunit:

    vendor/bin/phpunit
