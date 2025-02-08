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

use Hyperf\Collection\Arr;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class DbQueryExecutedListener extends InstrumentationListener
{
    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        match ($event::class) {
            QueryExecuted::class => $this->onQueryExecuted($event),
            default => null,
        };
    }

    protected function onQueryExecuted(QueryExecuted $event): void
    {
        if (! $this->switcher->isTracingEnabled('db_query')) {
            return;
        }

        // db query span end time
        $nowInNs = (int) (microtime(true) * 1E9);

        // combine sql and bindings
        $sql = $this->config->get('opentelemetry.instrumentation.features.db_query.options.combine_sql_and_bindings', false)
            ? $this->combineSqlAndBindings($event)
            : $event->sql;

        $span = $this->instrumentation->tracer()->spanBuilder('sql ' . $event->sql)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $event->time))
            ->startSpan();

        $span->setAttributes([
            TraceAttributes::DB_SYSTEM_NAME => $event->connection->getDriverName(),
            TraceAttributes::DB_NAMESPACE => $event->connection->getDatabaseName(),
            TraceAttributes::DB_OPERATION_NAME => Str::upper(Str::before($event->sql, ' ')),
            TraceAttributes::DB_USER => $event->connection->getConfig('username'),
            TraceAttributes::DB_QUERY_TEXT => $sql,
            TraceAttributes::SERVER_ADDRESS => $event->connection->getConfig('host'),
            TraceAttributes::SERVER_PORT => $event->connection->getConfig('port'),
        ]);

        if ($event->result instanceof Throwable) {
            $this->recordException($span, $event->result);
        }

        $span->end();
    }

    protected function combineSqlAndBindings(QueryExecuted $event): string
    {
        $sql = $event->sql;
        if (! Arr::isAssoc($event->bindings)) {
            foreach ($event->bindings as $value) {
                $sql = Str::replaceFirst('?', "'{$value}'", $sql);
            }
        }

        return $sql;
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}
