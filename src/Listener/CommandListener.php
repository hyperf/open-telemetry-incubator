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

use Carbon\Carbon;
use Hyperf\Command\Event\AfterExecute;
use Hyperf\Command\Event\BeforeHandle;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;

use function Hyperf\Coroutine\defer;

class CommandListener extends InstrumentationListener
{
    public function listen(): array
    {
        return [
            BeforeHandle::class,
            AfterExecute::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $this->switcher->isTracingEnabled('command')) {
            return;
        }

        match ($event::class) {
            BeforeHandle::class => $this->onBeforeHandle($event),
            AfterExecute::class => $this->onAfterExecute($event),
            default => null,
        };
    }

    protected function onBeforeHandle(BeforeHandle $event): void
    {
        $parent = Context::getCurrent();
        $span = $this->instrumentation->tracer()->spanBuilder($event->getCommand()->getName())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes([
                TraceAttributes::PROCESS_COMMAND => $event->getCommand()->getName(),
                TraceAttributes::PROCESS_COMMAND_ARGS => $event->getCommand()->getDefinition()->getArguments(),
                TraceAttributes::PROCESS_CREATION_TIME => Carbon::now()->toIso8601String(),
                TraceAttributes::PROCESS_EXECUTABLE_NAME => $event->getCommand()->getName(),
            ])
            ->startSpan();

        Context::storage()->attach($span->storeInContext($parent));
    }

    protected function onAfterExecute(AfterExecute $event): void
    {
        if (! $scope = Context::storage()->scope()) {
            return;
        }
        defer(function () use ($scope) {
            $scope->detach();
        });

        $span = Span::fromContext($scope->context());
        if (! $span->isRecording()) {
            return;
        }
        defer(function () use ($span) {
            $span->end();
        });

        // attributes
        $span->setAttributes([
            // TraceAttributes::PROCESS_EXIT_CODE => $event->getCommand()->getExitCode(), // not available in AfterExecute event
            TraceAttributes::PROCESS_EXIT_TIME => Carbon::now()->toIso8601String(),
        ]);

        // status
        if (($e = $event->getThrowable()) !== null) {
            $this->recordException($span, $e);
        }
    }
}
