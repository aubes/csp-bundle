<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

use Aubes\CSPBundle\Report\ReportTo;

class CSPPolicy
{
    protected ?ReportTo $reportTo;
    protected array $policies = [];
    protected bool $reportOnly;
    protected bool $bcSupport;

    public function __construct(?ReportTo $reportTo, array $policies, bool $reportOnly, bool $bcSupport)
    {
        $this->reportTo = $reportTo;
        $this->reportOnly = $reportOnly;
        $this->bcSupport = $bcSupport;

        foreach ($policies as $directive => $policy) {
            foreach ($policy as $source) {
                $this->addPolicy($directive, $source);
            }
        }
    }

    public function addPolicy(string $directive, string $source): void
    {
        if (!\in_array($directive, CSPDirective::ALL)) {
            throw new \InvalidArgumentException('Unknown directive ' . $directive);
        }

        if (\array_key_exists($source, CSPSource::ALL)) {
            $source = CSPSource::ALL[$source];
        }

        $this->policies[$directive][] = $source;
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

    public function render(): string
    {
        $output = [];

        foreach ($this->policies as $directive => $policy) {
            $output[] = $directive . ' ' . \implode(' ', $policy);
        }

        if ($this->reportTo !== null) {
            $output[] = 'report-to ' . $this->reportTo->getGroupName();

            if ($this->isBCSupport()) {
                $endpoints = [];
                foreach ($this->reportTo->getUrlEndpoints(false) as $endpoint) {
                    $endpoints[] = $endpoint;
                }

                $output[] = 'report-uri ' . \implode(' ', $endpoints);
            }
        }

        return \implode('; ', $output);
    }
}
