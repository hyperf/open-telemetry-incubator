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

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

class Switcher
{
    public function __construct(
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly ConfigInterface $config,
    ) {
    }

    /**
     * Check if the instrumentation is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config->get('opentelemetry.instrumentation.enabled', true);
    }

    /**
     * Check if the tracing is enabled.
     */
    public function isTracingEnabled(?string $key = null): bool
    {
        if ($key === null) {
            return $this->instrumentation->tracer()->isEnabled()
                    && $this->isEnabled()
                    && $this->config->get('opentelemetry.instrumentation.tracing', true);
        }

        return $this->isTracingEnabled()
                && $this->config->get("opentelemetry.instrumentation.features.{$key}.enabled", true);
    }
}
