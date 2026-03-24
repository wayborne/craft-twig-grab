<?php

namespace wayborne\twiggrab\twig;

use wayborne\twiggrab\twig\nodes\CommentedIncludeNode;
use wayborne\twiggrab\twig\nodes\SourceCommentEndNode;
use wayborne\twiggrab\twig\nodes\SourceCommentStartNode;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\IncludeNode;
use Twig\Node\EmbedNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class GrabNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ModuleNode) {
            return $this->handleModuleNode($node);
        }

        if ($node instanceof BlockNode) {
            return $this->handleBlockNode($node);
        }

        // Static includes only (not embeds, not dynamic)
        if ($node instanceof IncludeNode && !$node instanceof EmbedNode) {
            return $this->handleIncludeNode($node);
        }

        return $node;
    }

    private function handleModuleNode(ModuleNode $node): ModuleNode
    {
        // Skip templates that extend another template — they produce no direct output
        if ($node->hasNode('parent')
            && !($node->getNode('parent') instanceof ConstantExpression
                && $node->getNode('parent')->getAttribute('value') === false)
        ) {
            return $node;
        }

        $source = $node->getSourceContext();
        $name = $source ? $source->getName() : 'unknown';

        $node->setNode('display_start', new Node([
            new SourceCommentStartNode($name, 'template', $node->getTemplateLine()),
        ]));
        $node->setNode('display_end', new Node([
            new SourceCommentEndNode($name, 'template', $node->getTemplateLine()),
        ]));

        return $node;
    }

    private function handleBlockNode(BlockNode $node): BlockNode
    {
        $blockName = $node->getAttribute('name');
        $source = $node->getSourceContext();
        $templateName = $source ? $source->getName() : 'unknown';

        $node->setNode('display_start', new Node([
            new SourceCommentStartNode($templateName, 'block', $node->getTemplateLine(), $blockName),
        ]));
        $node->setNode('display_end', new Node([
            new SourceCommentEndNode($templateName, 'block', $node->getTemplateLine()),
        ]));

        return $node;
    }

    private function handleIncludeNode(IncludeNode $node): Node
    {
        $expr = $node->getNode('expr');

        // Only wrap static includes (literal template name)
        if (!$expr instanceof ConstantExpression) {
            return $node;
        }

        return new CommentedIncludeNode($node);
    }

    public function getPriority(): int
    {
        return 10; // After Twig's built-in profiler (0)
    }
}
