<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Event;

class CSPViolationEvent
{
    /**
     * @param array<mixed> $report
     */
    public function __construct(
        public readonly string $group,
        public readonly array $report,
    ) {
    }
}
