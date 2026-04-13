<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Listener;

use Aubes\CSPBundle\Event\CSPViolationEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class CSPViolationLogListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = LogLevel::WARNING,
    ) {
    }

    public function __invoke(CSPViolationEvent $event): void
    {
        $this->logger->log($this->level, 'csp_report', ['extra' => [
            'group' => $event->group,
            'report' => $event->report,
        ]]);
    }
}
