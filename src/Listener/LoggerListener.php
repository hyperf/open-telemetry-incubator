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

use Hyperf\Event\Contract\ListenerInterface;

class LoggerListener extends InstrumentationListener
{
    public function listen(): array
    {
        return [
        ];
    }

    public function process(object $event): void
    {
        // todo: implement process() method
    }
}
