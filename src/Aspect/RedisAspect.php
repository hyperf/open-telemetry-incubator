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

namespace Hyperf\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Redis\Redis;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class RedisAspect extends AbstractAspect
{
    public array $classes = [
        Redis::class . '::__call',
    ];

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if (
            class_exists('Hyperf\Redis\Event\CommandExecuted')
            || ! $this->switcher->isTracingEnabled('redis')
        ) {
            return $proceedingJoinPoint->process();
        }

        $args = $proceedingJoinPoint->arguments['keys'];
        $command = Str::lower($args['name']);
        $commandFull = $command . ' ' . $this->buildCommandArguments($args['arguments']);
        $poolName = (fn () => $this->poolName ?? 'default')->call($proceedingJoinPoint->getInstance());

        $span = $this->instrumentation->tracer()->spanBuilder($command)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        // todo: add more attributes
        $span->setAttributes([
            TraceAttributes::DB_SYSTEM_NAME => 'redis',
            TraceAttributes::DB_OPERATION_NAME => Str::upper($command),
            TraceAttributes::DB_QUERY_TEXT => $commandFull,
            'hyperf.redis.pool' => $poolName,
        ]);

        try {
            $result = $proceedingJoinPoint->process();
        } catch (Throwable $e) {
            $this->recordException($span, $e);

            throw $e;
        } finally {
            $span->end();
        }

        return $result;
    }

    /**
     * Build the command arguments.
     */
    private function buildCommandArguments(array $args): string
    {
        $callback = static function (array $args) use (&$callback) {
            $result = '';
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $result .= $callback($arg);
                } elseif (! is_object($arg)) { // fix: redis subscribe command
                    $result .= $arg . ' ';
                }
            }

            return $result;
        };

        return $callback($args);
    }
}
