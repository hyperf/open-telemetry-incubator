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

namespace Hyperf\OpenTelemetry\Concerns;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

trait SpanRecordThrowable
{
    /**
     * Record exception to span.
     */
    protected function spanRecordException(SpanInterface $span, ?Throwable $e = null): void
    {
        if ($e === null) {
            return;
        }

        $span->setAttributes([
            TraceAttributes::EXCEPTION_TYPE => get_class($e),
            TraceAttributes::EXCEPTION_MESSAGE => $e->getMessage(),
            TraceAttributes::EXCEPTION_STACKTRACE => $e->getTraceAsString(),
            TraceAttributes::CODE_FUNCTION_NAME => $e->getFile() . ':' . $e->getLine(),
            TraceAttributes::CODE_LINE_NUMBER => $e->getLine(),
        ]);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $span->recordException($e);
    }
}
