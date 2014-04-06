<?php

namespace JmesPath\Tests\Tree;

use JmesPath\Tree\TreeInterpreter;

/**
 * @covers JmesPath\Tree\TreeInterpreter
 */
class TreeInterpreterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid relational operator
     */
    public function testEnsuresComparatorIsValid()
    {
        $t = new TreeInterpreter();
        $t->visit(array(
            'type' => 'comparator',
            'relation' => 'foo',
            'children' => array(
                array('type' => 'literal', 'value' => 1),
                array('type' => 'literal', 'value' => 1)
            )
        ), array(), array(
            'runtime' => \JmesPath\createRuntime()
        ));
    }

    public function testReturnsNullWhenMergingNonArray()
    {
        $t = new TreeInterpreter();
        $this->assertNull($t->visit(array(
            'type' => 'flatten',
            'children' => array(
                array('type' => 'literal', 'value' => 1),
                array('type' => 'literal', 'value' => 1)
            )
        ), array(), array(
            'runtime' => \JmesPath\createRuntime()
        )));
    }
}
