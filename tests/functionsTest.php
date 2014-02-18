<?php

namespace JmesPath\Tests;

class functionsTest extends \PHPUnit_Framework_TestCase
{
    public function testSearchesInput()
    {
        $data = array('foo' => 123);
        $this->assertEquals(123, \JmesPath\search('foo', $data));
        $this->assertEquals(123, \JmesPath\search('foo', $data));
    }

    public function testCreatesCompileRuntime()
    {
        $r = \JmesPath\createRuntime(array('compile' => 'compiled'));
        $this->assertInstanceOf('JmesPath\Runtime\CompilerRuntime', $r);
        $r->search('foo.bar.fn', array());
        $file = sprintf('%s/../compiled/jmespath_%s.php', __DIR__, md5('foo.bar.fn'));
        $this->assertFileExists($file);
        $r->clearCache();
        $this->assertFileNotExists($file);
    }

    public function testCreatesCompileRuntimeUsingSysTempDir()
    {
        $r = \JmesPath\createRuntime(array('compile' => true));
        $this->assertInstanceOf('JmesPath\Runtime\CompilerRuntime', $r);
        $r->search('foo.baz.fn', array());
        $file = sprintf('%s/jmespath_%s.php', sys_get_temp_dir(), md5('foo.baz.fn'));
        $this->assertFileExists($file);
        $r->clearCache();
        $this->assertFileNotExists($file);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "compile" must be a string or Boolean
     */
    public function testEnsuresCompileArgIsValid()
    {
        \JmesPath\createRuntime(array('compile' => 2));
    }
}
