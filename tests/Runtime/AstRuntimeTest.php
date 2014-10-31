<?php
namespace JmesPath\Tests\Runtime;

use JmesPath\Runtime\AstRuntime;

class AstRuntimeTest extends \PHPUnit_Framework_TestCase
{
    public function testClearsCache()
    {
        $r = new AstRuntime();
        $r->search('foo', array());
        $this->assertNotEmpty($this->readAttribute($r, 'cache'));
        $r->clearCache();
        $this->assertEmpty($this->readAttribute($r, 'cache'));
        $this->assertEquals(0, $this->readAttribute($r, 'cachedCount'));
    }
}
