<?php
namespace JmesPath\Tests\Tree;

use JmesPath\TreeCompiler;
use PHPUnit\Framework\TestCase;

/**
 * @covers JmesPath\Tree\TreeCompiler
 */
class TreeCompilerTest extends TestCase
{
    public function testCreatesSourceCode(): void
    {
        $t = new TreeCompiler();
        $source = $t->visit(
            ['type' => 'field', 'value' => 'foo'],
            'testing',
            'foo'
        );
        $this->assertStringContainsString('<?php', $source);
        $this->assertStringContainsString('$value = isset($value->{\'foo\'}) ? $value->{\'foo\'} : null;', $source);
        $this->assertStringContainsString('$value = isset($value[\'foo\']) ? $value[\'foo\'] : null;', $source);
    }
}
