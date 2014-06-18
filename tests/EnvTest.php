<?php
namespace JmesPath\Tests;

class EnvTest extends \PHPUnit_Framework_TestCase
{
    public function testSearchesInput()
    {
        $data = array('foo' => 123);
        $this->assertEquals(123, \JmesPath::search('foo', $data));
        $this->assertEquals(123, \JmesPath::search('foo', $data));
    }
}
