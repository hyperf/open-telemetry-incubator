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

use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Propagator\HeadersPropagator;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\RequestInterface;
use Throwable;

class GuzzleClientAspect extends AbstractAspect
{
    public array $classes = [
        Client::class . '::transfer',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switcher->isTracingEnabled('guzzle') === false) {
            return $proceedingJoinPoint->process();
        }

        /** @var RequestInterface $request */
        $request = $proceedingJoinPoint->arguments['keys']['request'];
        $parentContext = Context::getCurrent();
        $span = $this->instrumentation->tracer()
            ->spanBuilder($request->getMethod() . ' ' . $request->getUri()->getPath())
            ->setParent($parentContext)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $context = $span->storeInContext($parentContext);

        TraceContextPropagator::getInstance()->inject($request, HeadersPropagator::instance(), $context);

        $onStats = $proceedingJoinPoint->arguments['keys']['options']['on_stats'] ?? null;

        $proceedingJoinPoint->arguments['keys']['options']['on_stats'] = function (TransferStats $stats) use ($span, $onStats) {
            // request
            $request = $stats->getRequest();
            $span->setAttributes([
                TraceAttributes::HTTP_REQUEST_METHOD => strtoupper($request->getMethod()),
                TraceAttributes::URL_FULL => (string) $request->getUri(),
                TraceAttributes::URL_PATH => $request->getUri()->getPath(),
                TraceAttributes::URL_SCHEME => $request->getUri()->getScheme(),
                TraceAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
                TraceAttributes::SERVER_PORT => $request->getUri()->getPort(),
                TraceAttributes::USER_AGENT_ORIGINAL => $request->getHeaderLine('User-Agent'),
                TraceAttributes::URL_QUERY => $request->getUri()->getQuery(),
                ...$this->transformHeaders('request', $request->getHeaders()),
            ]);

            // response
            if ($response = $stats->getResponse()) {
                $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->getHeaderLine('Content-Length'));
                $span->setAttributes($this->transformHeaders('response', $response->getHeaders()));

                if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }
            }
            
            if (($t = $stats->getHandlerErrorData()) instanceof Throwable) {
                $this->recordException($span, $t);
            }

            $span->end();

            if (is_callable($onStats)) {
                $onStats($stats);
            }
        };

        return $proceedingJoinPoint->process();
    }

    /**
     * Transform headers to OpenTelemetry attributes.
     *
     * @param array<array<string>> $headers
     * @return array<string, array<string>>
     */
    private function transformHeaders(string $type, array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $key = Str::lower($key);
            if ($this->canTransformHeaders($type, $key)) {
                $result["http.{$type}.header.{$key}"] = $value;
            }
        }

        return $result;
    }

    private function canTransformHeaders(string $type, string $key): bool
    {
        $headers = (array) $this->config->get("open-telemetry.instrumentation.features.guzzle.options.headers.{$type}", ['*']);
        foreach ($headers as $header) {
            if (Str::is(Str::lower($header), $key)) {
                return true;
            }
        }

        return false;
    }
}
