<?php
namespace JmesPath\Tests\Runtime;

use JmesPath\Parser;
use JmesPath\Runtime\AstRuntime;
use JmesPath\Tree\TreeInterpreter;
use JmesPath\Lexer;

class AstRuntimeTest extends \PHPUnit_Framework_TestCase
{
    public function testClearsCache()
    {
        $r = new AstRuntime([
            'parser'      => new Parser(new Lexer()),
            'interpreter' => new TreeInterpreter()
        ]);
        $r->search('foo', array());
        $this->assertNotEmpty($this->readAttribute($r, 'cache'));
        $r->clearCache();
        $this->assertEmpty($this->readAttribute($r, 'cache'));
        $this->assertEquals(0, $this->readAttribute($r, 'cachedCount'));
    }
}
