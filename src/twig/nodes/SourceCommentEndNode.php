<?php

namespace wayborne\twiggrab\twig\nodes;

use Twig\Compiler;
use Twig\Node\Node;

class SourceCommentEndNode extends Node
{
    public function __construct(string $templateName, string $type, int $line)
    {
        parent::__construct([], [
            'template_name' => $templateName,
            'type' => $type,
        ], $line);
    }

    public function compile(Compiler $compiler): void
    {
        $json = json_encode([
            'type' => $this->getAttribute('type'),
            'template' => $this->getAttribute('template_name'),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:end " . $json . " -->';\n")
            ->outdent()
            ->write("}\n");
    }
}
