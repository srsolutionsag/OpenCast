<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\Container;

use srag\Plugins\Opencast\API\API;
use srag\Plugins\Opencast\API\OpencastAPI;
use srag\Plugins\Opencast\API\Config;
use srag\Plugins\Opencast\Model\Config\PluginConfig;
use srag\Plugins\Opencast\API\Handlers;
use srag\Plugins\Opencast\Model\Cache\Services;
use srag\Plugins\Opencast\Model\Cache\Config as CacheConfig;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\DI\OpencastDIC;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;
use ILIAS\DI\HTTPServices;
use srag\Plugins\Opencast\Util\Locale\Translator;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 *
 * @internal
 *
 * We use this dependency injection container at the moment as follows:
 * We put dependencies that we need in code into this container whenever possible and get it from there.
 * The convention is that we register the dependency with its FQDN in the container, if possible always with an
 * interface, which simplifies the exchange of the implementation.
 */
final class Init
{
    /**
     * @var Container|null
     */
    private static $container = null;

    /**
     *  @deprecated This method is currently used in many places in the plugin. However, the goal should be to use this
     *  initialization of the container a maximum of once and to inject the dependencies (or the entire container)
     *  everywhere as constructor arguments. in the end, this leaves only very few entry points at which the container
     *  must be effectively built and we get rid of all these static calls.
     */
    public static function init(?\ILIAS\DI\Container $ilias_container = null): Container
    {
        if (self::$container !== null) {
            return self::$container;
        }
        PluginConfig::setApiSettings();

        $opencast_container = new Container();
        $legacy_container = OpencastDIC::getInstance();

        // ILIAS Dependencies
        $opencast_container->glue(
            \ILIAS\DI\Container::class,
            function () use ($ilias_container) {
                return $ilias_container;
            }
        );

        $opencast_container->glue(HTTPServices::class, function () use ($ilias_container) {
            return $ilias_container->http();
        });

        // Plugin Instance
        $opencast_container->glue(
            \ilOpenCastPlugin::class,
            static function () {
                return \ilOpenCastPlugin::getInstance();
            }
        );

        // Legacy Container
        $opencast_container->glue(
            OpencastDIC::class,
            static function () use ($legacy_container) {
                return $legacy_container;
            }
        );

        // Translator
        $opencast_container->glue(
            Translator::class,
            static function () use ($opencast_container) {
                return new Translator($opencast_container);
            }
        );

        // Plugin Dependencies
        $opencast_container->glue(Config::class, fn(): Config => new Config(
            Handlers::getHandlerStack(),
            PluginConfig::getConfig(PluginConfig::F_API_BASE) ?? 'https://stable.opencast.org/api',
            PluginConfig::getConfig(PluginConfig::F_CURL_USERNAME) ?? 'admin',
            PluginConfig::getConfig(PluginConfig::F_CURL_PASSWORD) ?? 'opencast',
            PluginConfig::getConfig(PluginConfig::F_API_VERSION) ?? '1.9.0',
            0,
            0,
            PluginConfig::getConfig(PluginConfig::F_PRESENTATION_NODE) ?? null
        ));

        $opencast_container->glue(API::class, function () use ($opencast_container): OpencastAPI {
            return new OpencastAPI($opencast_container[Config::class]);
        });

        $opencast_container->glue(Services::class, function () use ($opencast_container): Services {
            $use_cache = (int) PluginConfig::getConfig(PluginConfig::F_ACTIVATE_CACHE);
            // map to caching settings
            switch ($use_cache) {
                case PluginConfig::CACHE_DISABLED:
                default:
                    $activated = false;
                    $adaptor = CacheConfig::PHPSTATIC;
                    break;
                case PluginConfig::CACHE_APCU:
                    $activated = true;
                    $adaptor = CacheConfig::APCU;
                    break;
                case PluginConfig::CACHE_DATABASE:
                    $activated = true;
                    $adaptor = CacheConfig::DATABASE;
                    break;
            }

            $config = new CacheConfig(
                $adaptor,
                $activated
            );

            return new Services(
                $config,
                $opencast_container[\ILIAS\DI\Container::class]->database()
            );
        });

        $opencast_container->glue(EventAPIRepository::class, function () use ($opencast_container, $legacy_container) {
            return new EventAPIRepository(
                $opencast_container->get(Services::class),
                $legacy_container->get('event_parser'),
                $legacy_container->get('ingest_service')
            );
        });

        $opencast_container->glue(SeriesAPIRepository::class, function () use ($opencast_container, $legacy_container) {
            return new SeriesAPIRepository(
                $opencast_container->get(Services::class),
                $legacy_container->get('series_parser'),
                $legacy_container->get('acl_utils'),
                $legacy_container->get('md_factory'),
                $legacy_container->get('md_parser')
            );
        });

        return self::$container = $opencast_container;
    }
}
