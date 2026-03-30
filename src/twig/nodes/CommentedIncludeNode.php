<?php

namespace wayborne\twiggrab\twig\nodes;

use Twig\Compiler;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;

/**
 * Wraps an IncludeNode or EmbedNode with twig-grab start/end comments.
 * Handles both static (compile-time) and dynamic (runtime) template names.
 *
 * For EmbedNode, the template name must be passed explicitly via $templateName
 * because EmbedNode's expr node is a placeholder ('not_used').
 */
class CommentedIncludeNode extends Node
{
    public function __construct(Node $original, string $type = 'include', ?string $templateName = null)
    {
        parent::__construct(
            ['original' => $original],
            ['comment_type' => $type, 'template_name' => $templateName],
            $original->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $original = $this->getNode('original');
        $overrideName = $this->getAttribute('template_name');

        // Explicit name provided (used for embeds)
        if ($overrideName !== null) {
            $this->compileStatic($compiler, $original, $overrideName);
            return;
        }

        $expr = $original->getNode('expr');

        if ($expr instanceof ConstantExpression) {
            $this->compileStatic($compiler, $original, $expr->getAttribute('value'));
        } else {
            $this->compileDynamic($compiler, $original, $expr);
        }
    }

    /**
     * Static template name — JSON is baked into the compiled PHP as a string literal.
     */
    private function compileStatic(Compiler $compiler, Node $original, string $name): void
    {
        $type = $this->getAttribute('comment_type');
        $line = $original->getTemplateLine();
        $flags = JSON_UNESCAPED_SLASHES | JSON_HEX_APOS;

        $startJson = json_encode([
            'type' => $type,
            'template' => $name,
            'line' => $line,
        ], $flags);

        $endJson = json_encode([
            'type' => $type,
            'template' => $name,
        ], $flags);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:start " . $startJson . " -->';\n")
            ->outdent()
            ->write("}\n");

        $compiler->subcompile($original);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:end " . $endJson . " -->';\n")
            ->outdent()
            ->write("}\n");
    }

    /**
     * Dynamic template name — evaluates the expression once into a temp variable,
     * then uses it for both start and end comments.
     */
    private function compileDynamic(Compiler $compiler, Node $original, Node $expr): void
    {
        $type = $this->getAttribute('comment_type');
        $line = $original->getTemplateLine();

        // Evaluate the expression once to avoid repeated side effects
        $compiler
            ->write("\$__twigGrabName = ")
            ->subcompile($expr)
            ->raw(";\n");

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:start ' . json_encode(['type' => '$type', 'template' => \$__twigGrabName, 'line' => $line], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) . ' -->';\n")
            ->outdent()
            ->write("}\n");

        $compiler->subcompile($original);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:end ' . json_encode(['type' => '$type', 'template' => \$__twigGrabName], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) . ' -->';\n")
            ->outdent()
            ->write("}\n");
    }
}
