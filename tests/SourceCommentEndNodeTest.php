<?php

namespace wayborne\twiggrab\tests;

use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use wayborne\twiggrab\twig\nodes\SourceCommentEndNode;

class SourceCommentEndNodeTest extends TestCase
{
    private function compile(SourceCommentEndNode $node): string
    {
        $env = new Environment(new ArrayLoader([]));
        $compiler = new Compiler($env);
        $node->compile($compiler);

        return $compiler->getSource();
    }

    public function testCompilesEndComment(): void
    {
        $node = new SourceCommentEndNode('_pages/home.twig', 'template', 1);
        $source = $this->compile($node);

        $this->assertStringContainsString('twig-grab:end', $source);
        $this->assertStringContainsString('"type":"template"', $source);
        $this->assertStringContainsString('"template":"_pages/home.twig"', $source);
    }

    public function testEndCommentDoesNotContainLine(): void
    {
        $node = new SourceCommentEndNode('test.twig', 'template', 42);
        $source = $this->compile($node);

        // End comments should not include line numbers
        $this->assertStringNotContainsString('"line"', $source);
    }

    public function testGatedBehindEnabledFlag(): void
    {
        $node = new SourceCommentEndNode('test.twig', 'template', 1);
        $source = $this->compile($node);

        $this->assertStringContainsString('if (\wayborne\twiggrab\TwigGrab::$enabled)', $source);
    }
}
