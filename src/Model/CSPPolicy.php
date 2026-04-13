<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Model;

use Aubes\CSPBundle\Enum\CSPDirective;
use Aubes\CSPBundle\Enum\CSPSource;
use Aubes\CSPBundle\Report\ReportTo;

class CSPPolicy
{
    /** @var array<string, list<string>> */
    private array $policies = [];

    /**
     * @param array<string, list<string>> $policies
     */
    public function __construct(
        private readonly ?ReportTo $reportTo,
        array $policies,
        private readonly bool $reportOnly,
        private readonly bool $bcSupport,
    ) {
        foreach ($policies as $directive => $policy) {
            foreach ($policy as $source) {
                $this->addPolicy($directive, $source);
            }
        }
    }

    public function addPolicy(string $directive, string $source): void
    {
        if (CSPDirective::tryFrom($directive) === null) {
            throw new \InvalidArgumentException('Unknown directive ' . $directive);
        }

        $cspSource = CSPSource::tryFrom($source);
        if ($cspSource !== null) {
            $source = $cspSource->quoted();
        }

        if (!\in_array($source, $this->policies[$directive] ?? [], true)) {
            $this->policies[$directive][] = $source;
        }
    }

    public function isReportOnly(): bool
    {
        return $this->reportOnly;
    }

    public function isBCSupport(): bool
    {
        return $this->bcSupport;
    }

    public function getReportTo(): ?ReportTo
    {
        return $this->reportTo;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }

    public function render(): string
    {
        $output = [];

        foreach ($this->policies as $directive => $policy) {
            $output[] = $directive . ' ' . \implode(' ', $policy);
        }

        if ($this->reportTo !== null) {
            $output[] = 'report-to ' . $this->reportTo->getGroupName();

            if ($this->isBCSupport()) {
                $endpoints = $this->reportTo->getUrlEndpoints(false);
                $output[] = 'report-uri ' . \implode(' ', $endpoints);
            }
        }

        return \implode('; ', $output);
    }
}
