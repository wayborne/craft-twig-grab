<?php

namespace wayborne\twiggrab\tests;

use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use wayborne\twiggrab\twig\nodes\SourceCommentStartNode;

class SourceCommentStartNodeTest extends TestCase
{
    private function compile(SourceCommentStartNode $node): string
    {
        $env = new Environment(new ArrayLoader([]));
        $compiler = new Compiler($env);
        $node->compile($compiler);

        return $compiler->getSource();
    }

    public function testCompilesTemplateStartComment(): void
    {
        $node = new SourceCommentStartNode('_pages/home.twig', 'template', 1);
        $source = $this->compile($node);

        $this->assertStringContainsString('twig-grab:start', $source);
        $this->assertStringContainsString('"type":"template"', $source);
        $this->assertStringContainsString('"template":"_pages/home.twig"', $source);
        $this->assertStringContainsString('"line":1', $source);
        $this->assertStringContainsString('TwigGrab::$enabled', $source);
    }

    public function testCompilesBlockStartCommentWithBlockName(): void
    {
        $node = new SourceCommentStartNode('_layouts/base.twig', 'block', 45, 'content');
        $source = $this->compile($node);

        $this->assertStringContainsString('"type":"block"', $source);
        $this->assertStringContainsString('"block":"content"', $source);
        $this->assertStringContainsString('"template":"_layouts/base.twig"', $source);
    }

    public function testOmitsBlockNameWhenEmpty(): void
    {
        $node = new SourceCommentStartNode('_pages/home.twig', 'template', 1);
        $source = $this->compile($node);

        $this->assertStringNotContainsString('"block"', $source);
    }

    public function testGatedBehindEnabledFlag(): void
    {
        $node = new SourceCommentStartNode('test.twig', 'template', 1);
        $source = $this->compile($node);

        $this->assertStringContainsString('if (\wayborne\twiggrab\TwigGrab::$enabled)', $source);
    }

    public function testSlashesNotEscapedInTemplatePath(): void
    {
        $node = new SourceCommentStartNode('_components/cards/hero.twig', 'include', 5);
        $source = $this->compile($node);

        $this->assertStringContainsString('_components/cards/hero.twig', $source);
        $this->assertStringNotContainsString('_components\\/cards\\/hero.twig', $source);
    }
}
