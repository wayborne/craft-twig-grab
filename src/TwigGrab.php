<?php

namespace wayborne\twiggrab;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\helpers\FileHelper;
use craft\utilities\ClearCaches;
use craft\web\View;
use Twig\Cache\FilesystemCache;
use wayborne\twiggrab\assets\GrabAssetBundle;
use wayborne\twiggrab\models\Settings;
use wayborne\twiggrab\twig\TwigGrabExtension;
use yii\base\Event;
use yii\web\Response;

class TwigGrab extends Plugin
{
    public static TwigGrab $plugin;
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

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

        // Register the frontend JS asset bundle + config
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function () {
                $view = Craft::$app->getView();
                $view->registerAssetBundle(GrabAssetBundle::class);

                /** @var Settings $settings */
                $settings = $this->getSettings();
                $config = json_encode([
                    'shortcutKey' => $settings->shortcutKey,
                ], JSON_UNESCAPED_SLASHES);
                $view->registerJs("window.__twigGrabConfig=$config;", View::POS_HEAD);
            }
        );

        // Suppress annotations for non-HTML responses
        Event::on(
            Response::class,
            Response::EVENT_BEFORE_SEND,
            function (Event $event) {
                /** @var Response $response */
                $response = $event->sender;
                $contentType = $response->getHeaders()->get('Content-Type', '');
                if ($contentType && !str_contains($contentType, 'text/html')) {
                    self::$enabled = false;
                }
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function swapTwigCache(): void
    {
        $grabCachePath = $this->getGrabCachePath();

        if (!is_dir($grabCachePath)) {
            mkdir($grabCachePath, 0775, true);
        }

        $twig = Craft::$app->view->getTwig();
        $twig->setCache(new FilesystemCache($grabCachePath));
    }

    private function getGrabCachePath(): string
    {
        return Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'compiled_templates_grab';
    }

    protected function afterUninstall(): void
    {
        $grabCachePath = $this->getGrabCachePath();

        if (is_dir($grabCachePath)) {
            FileHelper::removeDirectory($grabCachePath);
        }
    }
}
