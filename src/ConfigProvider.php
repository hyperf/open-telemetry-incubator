<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\OpenTelemetry;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Resource\ResourceInfo;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        defined('BASE_PATH') || define('BASE_PATH', '');

        return [
            'dependencies' => [
                Contract\ExporterInterface::class => Exporter\OtlpExporter::class,
                CachedInstrumentation::class => Factory\CachedInstrumentationFactory::class,
                ResourceInfo::class => Factory\OTelResourceFactory::class,
            ],
            'listeners' => [
                Listener\DbQueryExecutedListener::class,
                Listener\ClientRequestListener::class,
                Listener\CommandListener::class,
                Listener\CrontabListener::class,
            ],
            'aspects' => [
                Aspect\RedisAspect::class,
                Aspect\GuzzleClientAspect::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for OpenTelemetry.',
                    'source' => __DIR__ . '/../publish/open-telemetry.php',
                    'destination' => BASE_PATH . '/config/autoload/open-telemetry.php',
                ],
            ],
        ];
    }
}
