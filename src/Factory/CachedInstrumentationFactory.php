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

namespace Hyperf\OpenTelemetry\Factory;

use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Context\ContextStorage as CtxStorage;
use Hyperf\OpenTelemetry\Contract\ExporterInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\SemConv\Version;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CachedInstrumentationFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CachedInstrumentation
    {
        Context::setStorage(new CtxStorage(new ContextStorage()));

        $container->get(ExporterInterface::class)->configure();

        return new CachedInstrumentation(
            name: 'hyperf/open-telemetry',
            schemaUrl: Version::VERSION_1_27_0->url(),
            attributes: [
                'instrumentation.name' => 'hyperf/open-telemetry',
            ],
        );
    }
}
