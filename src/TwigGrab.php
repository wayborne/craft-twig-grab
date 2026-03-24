<?php

namespace wayborne\twiggrab;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\utilities\ClearCaches;
use craft\web\View;
use wayborne\twiggrab\assets\GrabAssetBundle;
use wayborne\twiggrab\twig\TwigGrabExtension;
use yii\base\Event;

class TwigGrab extends Plugin
{
    public static TwigGrab $plugin;
    public string $schemaVersion = '1.0.0';

    /**
     * Static runtime flag checked by compiled templates before echoing annotations.
     */
    public static bool $enabled = false;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register grab cache directory with Craft's cache clearing (available on all requests)
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'twig-grab-compiled-templates',
                    'label' => 'Twig Grab compiled templates',
                    'action' => $this->getGrabCachePath(),
                ];
            }
        );

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

        if (!$request->getIsSiteRequest()) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            return;
        }

        // Logged-in user on a site request with plugin installed — activate
        self::$enabled = true;

        // Swap Twig's compiled template cache to the grab-annotated directory
        $this->swapTwigCache();

        // Register the Twig extension (NodeVisitor for annotations)
        Craft::$app->view->registerTwigExtension(new TwigGrabExtension());

        // Register the frontend JS asset bundle
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function () {
                Craft::$app->getView()->registerAssetBundle(GrabAssetBundle::class);
            }
        );

        // Suppress annotations for non-HTML responses
        Event::on(
            \yii\web\Response::class,
            \yii\web\Response::EVENT_BEFORE_SEND,
            function (\yii\base\Event $event) {
                $response = $event->sender;
                $contentType = $response->getHeaders()->get('Content-Type', '');
                if ($contentType && !str_contains($contentType, 'text/html')) {
                    self::$enabled = false;
                }
            }
        );
    }

    private function swapTwigCache(): void
    {
        $grabCachePath = $this->getGrabCachePath();

        if (!is_dir($grabCachePath)) {
            mkdir($grabCachePath, 0775, true);
        }

        $twig = Craft::$app->view->getTwig();
        $twig->setCache(new \Twig\Cache\FilesystemCache($grabCachePath));
    }

    private function getGrabCachePath(): string
    {
        return Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'compiled_templates_grab';
    }

    protected function afterUninstall(): void
    {
        $grabCachePath = $this->getGrabCachePath();

        if (is_dir($grabCachePath)) {
            Craft::$app->getPath()->removeDirectory($grabCachePath);
        }
    }
}
