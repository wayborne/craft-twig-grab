<?php

namespace wayborne\twiggrab\twig;

use wayborne\twiggrab\twig\nodes\CommentedIncludeNode;
use wayborne\twiggrab\twig\nodes\SourceCommentEndNode;
use wayborne\twiggrab\twig\nodes\SourceCommentStartNode;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\EmbedNode;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class GrabNodeVisitor implements NodeVisitorInterface
{
    /**
     * Maps embed index → embedded template name.
     * Populated from anonymous embed modules in enterNode,
     * consumed by EmbedNode handling in leaveNode.
     * @var array<string, string>
     */
    private array $embedTemplateNames = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        // Embedded templates are stored as attributes (not child nodes) of the main
        // ModuleNode, so the traverser never visits them. Extract the mapping here:
        // each anonymous module's index → its parent (the actual embedded template name).
        // Clear the map for each new module to prevent index collisions across templates
        // (embed indices are sequential integers starting from 0 per module).
        if ($node instanceof ModuleNode && $node->hasAttribute('embedded_templates')) {
            $this->embedTemplateNames = [];
            foreach ($node->getAttribute('embedded_templates') as $embedded) {
                if (!$embedded instanceof ModuleNode) {
                    continue;
                }
                $index = $embedded->getAttribute('index');
                if ($index === null || !$embedded->hasNode('parent')) {
                    continue;
                }
                $parent = $embedded->getNode('parent');
                if ($parent instanceof ConstantExpression && $parent->getAttribute('value') !== false) {
                    $this->embedTemplateNames[$index] = $parent->getAttribute('value');
                }
            }
        }

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

        // Embeds (must check before IncludeNode since EmbedNode extends it)
        if ($node instanceof EmbedNode) {
            $name = $this->embedTemplateNames[$node->getAttribute('index')] ?? null;
            return new CommentedIncludeNode($node, 'embed', $name);
        }

        // Includes (static and dynamic)
        if ($node instanceof IncludeNode) {
            return new CommentedIncludeNode($node);
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

    public function getPriority(): int
    {
        return 10; // After Twig's built-in profiler (0)
    }
}
