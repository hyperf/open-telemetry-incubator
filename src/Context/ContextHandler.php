<?php

/** @noinspection PhpComposerExtensionStubsInspection */

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

use Hyperf\Context\Context;
use Hyperf\Coroutine\Coroutine as Co;
use OpenTelemetry\Context\ExecutionContextAwareInterface;

/**
 * @internal
 *
 * @phan-file-suppress PhanUndeclaredClassMethod
 * @psalm-suppress UndefinedClass
 */
final class ContextHandler
{
    private ExecutionContextAwareInterface $storage;

    public function __construct(ExecutionContextAwareInterface $storage)
    {
        $this->storage = $storage;
    }

    public function switchToActiveCoroutine(): void
    {
        $cid = Co::id();
        if ($cid !== -1 && ! $this->isForked($cid)) {
            for ($pcid = $cid; ($pcid = Co::pid($pcid)) !== -1 && Co::exists($pcid) && ! $this->isForked($pcid););

            $this->storage->switch($pcid);
            $this->forkCoroutine($cid);
        }

        $this->storage->switch($cid);
    }

    public function splitOffChildCoroutines(): void
    {
        $pcid = Co::id();
        foreach (Co::list() as $cid) {
            if ($pcid === Co::pid($cid) && ! $this->isForked($cid)) {
                $this->forkCoroutine($cid);
            }
        }
    }

    private function isForked(int $cid): bool
    {
        return Context::has(__CLASS__, $cid);
    }

    private function forkCoroutine(int $cid): void
    {
        $this->storage->fork($cid);
        Context::set(__CLASS__, new ContextDestructor($this->storage, $cid), $cid);
    }
}
