<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Contract;

interface ExporterInterface
{
    public function configure(): void;
}
