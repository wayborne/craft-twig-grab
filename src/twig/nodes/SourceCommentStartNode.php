<?php

namespace wayborne\twiggrab\twig\nodes;

use Twig\Compiler;
use Twig\Node\Node;

class SourceCommentStartNode extends Node
{
    public function __construct(string $templateName, string $type, int $line, string $blockName = '')
    {
        parent::__construct([], [
            'template_name' => $templateName,
            'type' => $type,
            'block_name' => $blockName,
        ], $line);
    }

    public function compile(Compiler $compiler): void
    {
        $data = [
            'type' => $this->getAttribute('type'),
            'template' => $this->getAttribute('template_name'),
            'line' => $this->getTemplateLine(),
        ];

        $blockName = $this->getAttribute('block_name');
        if ($blockName !== '') {
            $data['block'] = $blockName;
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $compiler
            ->write("if (\\wayborne\\twiggrab\\TwigGrab::\$enabled) {\n")
            ->indent()
            ->write("echo '<!-- twig-grab:start " . addslashes($json) . " -->';\n")
            ->outdent()
            ->write("}\n");
    }
}
