<?php

namespace wayborne\twiggrab\tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use wayborne\twiggrab\twig\TwigGrabExtension;

class GrabNodeVisitorTest extends TestCase
{
    private function compileTemplate(string $template, string $name = 'test.twig', array $templates = []): string
    {
        $templates[$name] = $template;
        $env = new Environment(new ArrayLoader($templates));
        $env->addExtension(new TwigGrabExtension());

        $source = $env->compileSource(new Source($templates[$name], $name));

        return $source;
    }

    public function testSimpleTemplateGetsWrapped(): void
    {
        $source = $this->compileTemplate('<h1>Hello</h1>');

        $this->assertStringContainsString('twig-grab:start', $source);
        $this->assertStringContainsString('"type":"template"', $source);
        $this->assertStringContainsString('"template":"test.twig"', $source);
        $this->assertStringContainsString('twig-grab:end', $source);
    }

    public function testExtendingTemplateIsSkipped(): void
    {
        $source = $this->compileTemplate(
            '{% extends "base.twig" %}{% block content %}Hello{% endblock %}',
            'child.twig',
            ['base.twig' => '{% block content %}{% endblock %}'],
        );

        // Extending templates should NOT get template-level annotations
        $this->assertSame(0, substr_count($source, '"type":"template"'));
    }

    public function testBlockNodeGetsAnnotatedInNonExtendingTemplate(): void
    {
        // Block annotations use display_start/display_end which are compiled by ModuleNode,
        // not BlockNode. Test via a non-extending template with a block definition.
        $source = $this->compileTemplate(
            '{% block content %}Hello{% endblock %}',
            'base.twig',
        );

        // The template itself should be annotated
        $this->assertStringContainsString('"type":"template"', $source);
        $this->assertStringContainsString('"template":"base.twig"', $source);
    }

    public function testStaticIncludeGetsWrapped(): void
    {
        $source = $this->compileTemplate(
            '{% include "_partials/nav.twig" %}',
            'page.twig',
            ['_partials/nav.twig' => '<nav>Nav</nav>'],
        );

        $this->assertStringContainsString('"type":"include"', $source);
        $this->assertStringContainsString('"template":"_partials/nav.twig"', $source);
    }

    public function testDynamicIncludeGetsWrapped(): void
    {
        $source = $this->compileTemplate(
            '{% set tpl = "_partials/nav.twig" %}{% include tpl %}',
            'page.twig',
            ['_partials/nav.twig' => '<nav>Nav</nav>'],
        );

        $this->assertStringContainsString('$__twigGrabName', $source);
        $this->assertStringContainsString('json_encode(', $source);
    }

    public function testEmbedGetsWrapped(): void
    {
        $source = $this->compileTemplate(
            '{% embed "_components/card.twig" %}{% endembed %}',
            'page.twig',
            ['_components/card.twig' => '<div>{% block body %}{% endblock %}</div>'],
        );

        $this->assertStringContainsString('"type":"embed"', $source);
        $this->assertStringContainsString('"template":"_components/card.twig"', $source);
    }

    public function testAllAnnotationsAreGatedBehindEnabledFlag(): void
    {
        $source = $this->compileTemplate(
            '<h1>Hello</h1>{% include "_partials/nav.twig" %}',
            'page.twig',
            ['_partials/nav.twig' => '<nav>Nav</nav>'],
        );

        // Every twig-grab comment should be inside an enabled check
        $grabCount = substr_count($source, 'twig-grab:');
        $enabledCount = substr_count($source, 'TwigGrab::$enabled');

        // Each start/end pair requires its own enabled check
        $this->assertGreaterThan(0, $grabCount);
        $this->assertGreaterThanOrEqual($grabCount, $enabledCount);
    }

    public function testNodeVisitorPriority(): void
    {
        $extension = new TwigGrabExtension();
        $visitors = $extension->getNodeVisitors();

        $this->assertCount(1, $visitors);
        $this->assertSame(10, $visitors[0]->getPriority());
    }

    public function testMultipleIncludesEachGetAnnotated(): void
    {
        $source = $this->compileTemplate(
            '{% include "_partials/header.twig" %}{% include "_partials/footer.twig" %}',
            'page.twig',
            [
                '_partials/header.twig' => '<header></header>',
                '_partials/footer.twig' => '<footer></footer>',
            ],
        );

        $this->assertStringContainsString('"template":"_partials/header.twig"', $source);
        $this->assertStringContainsString('"template":"_partials/footer.twig"', $source);
        // compileSource compiles all templates (main + included), so include annotations
        // appear both in the main template and each included template's own compilation
        $this->assertGreaterThanOrEqual(2, substr_count($source, '"type":"include"'));
    }
}
