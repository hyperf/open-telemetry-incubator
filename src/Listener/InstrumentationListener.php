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

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\OpenTelemetry\Concerns\SpanRecordThrowable;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class InstrumentationListener.
 *
 * @property ConfigInterface $config
 * @property ContainerInterface $container
 * @property CachedInstrumentation $instrumentation
 * @property Switcher $switcher
 */
abstract class InstrumentationListener implements ListenerInterface
{
    use SpanRecordThrowable;

    protected readonly ConfigInterface $config;

    /**
     * InstrumentationListener constructor.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly Switcher $switcher,
    ) {
        $this->config = $this->container->get(ConfigInterface::class);
    }
}
