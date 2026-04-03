<?php

namespace wayborne\twiggrab\tests;

use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\IncludeNode;
use wayborne\twiggrab\twig\nodes\CommentedIncludeNode;

class CommentedIncludeNodeTest extends TestCase
{
    private function compile(CommentedIncludeNode $node): string
    {
        $env = new Environment(new ArrayLoader([]));
        $compiler = new Compiler($env);
        $node->compile($compiler);

        return $compiler->getSource();
    }

    private function makeIncludeNode(string $templateName): IncludeNode
    {
        $expr = new ConstantExpression($templateName, 1);
        $variables = new ConstantExpression(false, 1);

        return new IncludeNode($expr, $variables, false, false, 1);
    }

    public function testStaticIncludeWrapsWithComments(): void
    {
        $include = $this->makeIncludeNode('_components/card.twig');
        $node = new CommentedIncludeNode($include);
        $source = $this->compile($node);

        $this->assertStringContainsString('twig-grab:start', $source);
        $this->assertStringContainsString('twig-grab:end', $source);
        $this->assertStringContainsString('"type":"include"', $source);
        $this->assertStringContainsString('"template":"_components/card.twig"', $source);
    }

    public function testEmbedTypeUsesOverrideName(): void
    {
        $include = $this->makeIncludeNode('not_used');
        $node = new CommentedIncludeNode($include, 'embed', '_components/hero.twig');
        $source = $this->compile($node);

        $this->assertStringContainsString('"type":"embed"', $source);
        $this->assertStringContainsString('"template":"_components/hero.twig"', $source);
        // The twig-grab comments should reference the real template name, not 'not_used'
        $this->assertStringNotContainsString('twig-grab:start {"type":"embed","template":"not_used"', $source);
    }

    public function testDynamicIncludeUsesJsonEncodeAtRuntime(): void
    {
        $expr = new NameExpression('templateName', 1);
        $variables = new ConstantExpression(false, 1);
        $include = new IncludeNode($expr, $variables, false, false, 1);

        $node = new CommentedIncludeNode($include);
        $source = $this->compile($node);

        // Dynamic path: should use json_encode at runtime
        $this->assertStringContainsString('json_encode(', $source);
        $this->assertStringContainsString('$__twigGrabName', $source);
        $this->assertStringContainsString('twig-grab:start', $source);
        $this->assertStringContainsString('twig-grab:end', $source);
    }

    public function testStartCommentContainsLineNumber(): void
    {
        $include = $this->makeIncludeNode('_partials/nav.twig');
        $node = new CommentedIncludeNode($include);
        $source = $this->compile($node);

        $this->assertStringContainsString('"line":1', $source);
    }

    public function testEndCommentOmitsLineNumber(): void
    {
        $include = $this->makeIncludeNode('_partials/nav.twig');
        $node = new CommentedIncludeNode($include);
        $source = $this->compile($node);

        // Split on twig-grab:end and check that part doesn't have "line"
        $parts = explode('twig-grab:end', $source);
        $this->assertCount(2, $parts);
        $this->assertStringNotContainsString('"line"', $parts[1]);
    }
}
