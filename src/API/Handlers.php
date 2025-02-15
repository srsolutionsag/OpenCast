<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\API;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use xoctLog;

/**
 * Class srag\Plugins\Opencast\API\Handlers
 * This class is used to provide middlewares and handlers for OpencastAPI Client
 *
 * @copyright  2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class Handlers
{
    public static function getHandlerStack(): HandlerStack
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        self::registerMiddlewares($stack);
        return $stack;
    }

    private static function registerMiddlewares(HandlerStack $stack): void
    {
        $classmethods = get_class_methods(Handlers::class);
        // Request middlewares.
        foreach ($classmethods as $methodname) {
            // Registering methods that start with 'request'.
            if (strpos($methodname, 'request') === 0) {
                $middleware = self::$methodname();
                $stack->unshift($middleware);
            }
        }
    }

    private static function requestDebug(): \Closure
    {
        return static fn (callable $handler): \Closure => function (RequestInterface $request, array $options) use ($handler) {
            $path = $request->getUri()->getPath();
            $query = $request->getUri()->getQuery();
            $method = $request->getMethod();
            $completeUri = $request->getUri()->__toString();

            // Exclude requests.
            $isExcluded = array_filter(self::excludedRequests(), fn (array $excReq): bool => ltrim($excReq['path'], '/') === ltrim(
                $path,
                '/'
            ) && $excReq['query'] === $query && $excReq['method'] === $method);
            if (!empty($isExcluded)) {
                return $handler($request, $options);
            }
            $xoctLog = xoctLog::getInstance();
            $xoctLog->write('execute *************************************************', xoctLog::DEBUG_LEVEL_1);
            $xoctLog->write(urldecode($completeUri), xoctLog::DEBUG_LEVEL_1);
            $xoctLog->write($request->getMethod(), xoctLog::DEBUG_LEVEL_1);
            if (xoctLog::getLogLevel() >= xoctLog::DEBUG_LEVEL_3) {
                $options[RequestOptions::DEBUG] = fopen(xoctLog::getFullPath(), 'a');
            }
            $options[RequestOptions::ON_STATS] = self::statsCallback();
            return $handler($request, $options);
        };
    }

    private static function statsCallback(): \Closure
    {
        return static function (TransferStats $stats): void {
            $time = $stats->getTransferTime();
            $handlerStats = $stats->getHandlerStats();

            $i = 1000;

            xoctLog::getInstance()->write(
                'CONNECT_TIME: ' . round($handlerStats["connect_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
            xoctLog::getInstance()->write(
                'NAMELOOKUP_TIME: ' . round($handlerStats["namelookup_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
            xoctLog::getInstance()->write(
                'REDIRECT_TIME: ' . round($handlerStats["redirect_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
            xoctLog::getInstance()->write(
                'STARTTRANSFER_TIME: ' . round($handlerStats["starttransfer_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
            xoctLog::getInstance()->write(
                'PRETRANSFER_TIME: ' . round($handlerStats["pretransfer_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
            xoctLog::getInstance()->write(
                'TOTAL_TIME: ' . round($handlerStats["total_time"] * $i, 2) . ' ms',
                xoctLog::DEBUG_LEVEL_1
            );
        };
    }

    private static function excludedRequests(): array
    {
        return [
            // This request is a necessary call for OpencastAPI class to get Ingest service up and running.
            // We could exclude this call here in order to avoid unwanted logs, however to get more accurate logs we turn this off for now!
            /* [
                'path' => 'services/services.json',
                'query' => 'serviceType=org.opencastproject.ingest',
                'method' => 'GET'
            ] */
        ];
    }
}
