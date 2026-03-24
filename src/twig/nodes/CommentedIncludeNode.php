<?php

namespace wayborne\twiggrab\twig\nodes;

use Twig\Compiler;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;

class CommentedIncludeNode extends Node
{
    public function __construct(IncludeNode $original)
    {
        parent::__construct(['original' => $original], [], $original->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $original = $this->getNode('original');
        $expr = $original->getNode('expr');
        $name = $expr->getAttribute('value');
        $line = $original->getTemplateLine();

        $startJson = json_encode([
            'type' => 'include',
            'template' => $name,
            'line' => $line,
        ], JSON_UNESCAPED_SLASHES);

        $endJson = json_encode([
            'type' => 'include',
            'template' => $name,
        ], JSON_UNESCAPED_SLASHES);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:start " . addslashes($startJson) . " -->';\n")
            ->outdent()
            ->write("}\n");

        $compiler->subcompile($original);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:end " . addslashes($endJson) . " -->';\n")
            ->outdent()
            ->write("}\n");
    }
}
