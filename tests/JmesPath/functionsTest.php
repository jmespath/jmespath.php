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

    public function testSearchesInputWithDebugInformation()
    {
        $data = array('foo' => 123);
        $resource = fopen('php://temp', 'r+');
        \Jmespath\debugSearch('foo', $data, $resource);
        rewind($resource);
        $output = stream_get_contents($resource);
        $this->assertContains('AST', $output);
    }
}
