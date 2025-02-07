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
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\OpenTelemetry\Concerns\SpanRecordThrowable;
use Hyperf\OpenTelemetry\Switcher;
use Hyperf\Redis\Event\CommandExecuted;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;

class RedisCommandExecutedListener implements ListenerInterface
{
    use SpanRecordThrowable;

    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly Switcher $switcher,
    ) {
        $this->switcher->isTracingEnabled('redis') && $this->setRedisEventEnable();
    }

    public function listen(): array
    {
        return [
            CommandExecuted::class,
        ];
    }

    /**
     * @param CommandExecuted|object $event
     */
    public function process(object $event): void
    {
        if (
            ! $event instanceof CommandExecuted
            || ! $this->switcher->isTracingEnabled('redis')
        ) {
            return;
        }

        $command = Str::lower($event->command);
        $fullCommand = $this->bindCommandParamaters($command, $event->parameters);

        $span = $this->instrumentation->tracer()->spanBuilder($command)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        // todo: add more attributes
        $span->setAttributes([
            TraceAttributes::DB_SYSTEM_NAME => 'redis',
            TraceAttributes::DB_OPERATION_NAME => Str::upper($command),
            TraceAttributes::DB_QUERY_TEXT => $fullCommand,
            'hyperf.redis.pool' => $event->connection,
        ]);

        if ($event->throwable) {
            $this->spanRecordException($span, $event->throwable);
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
    }

    private function setRedisEventEnable()
    {
        foreach ((array) $this->config->get('redis', []) as $connection => $_) {
            $this->config->set('redis.' . $connection . '.event.enable', true);
        }
    }

    /**
     * Bind the command paramaters.
     */
    private function bindCommandParamaters(string $command, array $paramaters = []): string
    {
        $callback = static function (array $paramaters) use (&$callback): string {
            $result = '';
            foreach ($paramaters as $arg) {
                if (is_array($arg)) {
                    $result .= $callback($arg);
                } elseif (! is_object($arg)) { // fix: redis subscribe command
                    $result .= $arg . ' ';
                }
            }

            return $result;
        };

        return $command . ' ' . $callback($paramaters);
    }
}
