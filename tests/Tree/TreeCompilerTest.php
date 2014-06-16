<?php
namespace JmesPath\Tests\Tree;

use JmesPath\Tree\TreeCompiler;

/**
 * @covers JmesPath\Tree\TreeCompiler
 * @covers JmesPath\Tree\AbstractTreeVisitor
 */
class TreeCompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresFunctionNameIsPassed()
    {
        $t = new TreeCompiler();
        $t->visit(array(), array());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid node encountered:
     */
    public function testThrowsExceptionForUnknownNodes()
    {
        $t = new TreeCompiler();
        $t->visit(array('type' => '1234'), array(), array('function_name' => 'abc'));
    }

    public function testCreatesSourceCode()
    {
        $t = new TreeCompiler();
        $source = $t->visit(array(
            'type' => 'field',
            'key' => 'foo'
        ), array(
            'foo' => 1
        ), array(
            'function_name' => 'testing'
        ));

        $this->assertContains('<?php', $source);
        $this->assertContains('is_array($value) && isset($value[\'foo\'])', $source);
    }
}
