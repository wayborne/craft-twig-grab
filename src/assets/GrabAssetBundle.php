<?php

namespace wayborne\twiggrab\assets;

use craft\web\AssetBundle;

class GrabAssetBundle extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'twig-grab.js',
        ];

        parent::init();
    }
}
