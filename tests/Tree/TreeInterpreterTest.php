<?php
namespace JmesPath\Tests\Tree;

use JmesPath\Runtime\AstRuntime;
use JmesPath\Tree\TreeInterpreter;

/**
 * @covers JmesPath\Tree\TreeInterpreter
 */
class TreeInterpreterTest extends \PHPUnit_Framework_TestCase
{
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
            'runtime' => new AstRuntime()
        )));
    }
}
