<?php

/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Context;

use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use OpenTelemetry\Context\ExecutionContextAwareInterface;

final class ContextStorage implements ContextStorageInterface, ExecutionContextAwareInterface
{
    /** @var ContextStorageInterface&ExecutionContextAwareInterface */
    private ContextStorageInterface $storage;
    private ContextHandler $handler;

    /**
     * @param ContextStorageInterface&ExecutionContextAwareInterface $storage
     */
    public function __construct(ContextStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->handler = new ContextHandler($storage);
    }

    public function fork($id): void
    {
        $this->handler->switchToActiveCoroutine();

        $this->storage->fork($id);
    }

    public function switch($id): void
    {
        $this->handler->switchToActiveCoroutine();

        $this->storage->switch($id);
    }

    public function destroy($id): void
    {
        $this->handler->switchToActiveCoroutine();

        $this->storage->destroy($id);
    }

    public function scope(): ?ContextStorageScopeInterface
    {
        $this->handler->switchToActiveCoroutine();

        if (($scope = $this->storage->scope()) === null) {
            return null;
        }

        return new ContextScope($scope, $this->handler);
    }

    public function current(): ContextInterface
    {
        $this->handler->switchToActiveCoroutine();

        return $this->storage->current();
    }

    public function attach(ContextInterface $context): ContextStorageScopeInterface
    {
        $this->handler->switchToActiveCoroutine();
        $this->handler->splitOffChildCoroutines();

        $scope = $this->storage->attach($context);

        return new ContextScope($scope, $this->handler);
    }
}
