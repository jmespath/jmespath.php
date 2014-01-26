<?php

namespace JmesPath\Tests\Runtime;

use JmesPath\Parser;
use JmesPath\Runtime\DefaultRuntime;
use JmesPath\Tree\TreeInterpreter;
use JmesPath\Lexer;

class DefaultRuntimeTest extends \PHPUnit_Framework_TestCase
{
    public function testClearsCache()
    {
        $r = new DefaultRuntime(new Parser(new Lexer()), new TreeInterpreter());
        $r->search('foo', array());
        $this->assertNotEmpty($this->readAttribute($r, 'cache'));
        $r->clearCache();
        $this->assertEmpty($this->readAttribute($r, 'cache'));
        $this->assertEquals(0, $this->readAttribute($r, 'cachedCount'));
    }
}
