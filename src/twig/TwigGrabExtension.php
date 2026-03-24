<?php

namespace wayborne\twiggrab\twig;

use Twig\Extension\AbstractExtension;

class TwigGrabExtension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [new GrabNodeVisitor()];
    }
}
