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

    public function testFromlessProjectionUsesCorrectGuard(): void
    {
        $t = new TreeCompiler();
        $source = $t->visit(
            [
                'type' => 'projection',
                'children' => [
                    ['type' => 'current'],
                    ['type' => 'current'],
                ],
            ],
            'testing',
            '[*]'
        );

        $this->assertStringContainsString(
            'if (!is_array($value) && !($value instanceof \\stdClass)) { $value = null; }',
            $source
        );
    }

    public function testEscapesFunctionName(): void
    {
        $compiler = new TreeCompiler();
        $source = $compiler->visit(
            [
                'type'     => 'function',
                'value'    => '" . $shouldNotBeCode . "',
                'children' => [],
            ],
            'testing',
            'example'
        );

        $this->assertStringContainsString(
            '$value = Fd::getInstance()->__invoke(\'" . $shouldNotBeCode . "\', $args);',
            $source
        );
        $this->assertStringNotContainsString(
            '__invoke("" . $shouldNotBeCode . "", $args);',
            $source
        );
    }
}
