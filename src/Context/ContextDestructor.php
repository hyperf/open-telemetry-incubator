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

namespace Hyperf\OpenTelemetry\Context;

use OpenTelemetry\Context\ExecutionContextAwareInterface;

/**
 * @internal
 */
final class ContextDestructor
{
    private ExecutionContextAwareInterface $storage;

    private int $cid;

    public function __construct(ExecutionContextAwareInterface $storage, int $cid)
    {
        $this->storage = $storage;
        $this->cid = $cid;
    }

    public function __destruct()
    {
        $this->storage->destroy($this->cid);
    }
}
