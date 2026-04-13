<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\DataCollector;

use Aubes\CSPBundle\CSP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class CSPDataCollector extends DataCollector
{
    public function __construct(
        private readonly CSP $csp,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $cspHeader = $response->headers->get('Content-Security-Policy', '');
        $cspRoHeader = $response->headers->get('Content-Security-Policy-Report-Only', '');
        $reportingEndpoints = $response->headers->get('Reporting-Endpoints', '');

        /** @var list<string> $groups */
        $groups = (array) $request->attributes->get('_csp_groups', []);

        $this->data = [
            'enabled' => $this->csp->isEnabled() && !$request->attributes->get('_csp_disabled', false),
            'groups' => $groups,
            'csp_header' => $cspHeader,
            'csp_ro_header' => $cspRoHeader,
            'reporting_endpoints' => $reportingEndpoints,
            'directives' => $this->parseHeader($cspHeader),
            'directives_ro' => $this->parseHeader($cspRoHeader),
            'directive_warnings' => $this->detectDirectiveConflicts($groups),
        ];
    }

    public function getName(): string
    {
        return 'csp';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->data['enabled'] ?? false);
    }

    /** @return list<string> */
    public function getGroups(): array
    {
        /** @var list<string> $groups */
        $groups = $this->data['groups'] ?? [];

        return $groups;
    }

    public function getCspHeader(): string
    {
        $value = $this->data['csp_header'] ?? '';

        return \is_string($value) ? $value : '';
    }

    public function getCspRoHeader(): string
    {
        $value = $this->data['csp_ro_header'] ?? '';

        return \is_string($value) ? $value : '';
    }

    public function getReportingEndpoints(): string
    {
        $value = $this->data['reporting_endpoints'] ?? '';

        return \is_string($value) ? $value : '';
    }

    /** @return array<string, string> */
    public function getDirectives(): array
    {
        /** @var array<string, string> $directives */
        $directives = $this->data['directives'] ?? [];

        return $directives;
    }

    /** @return array<string, string> */
    public function getDirectivesRo(): array
    {
        /** @var array<string, string> $directives */
        $directives = $this->data['directives_ro'] ?? [];

        return $directives;
    }

    /** @return list<array{directive: string, group: string, message: string}> */
    public function getDirectiveWarnings(): array
    {
        /** @var list<array{directive: string, group: string, message: string}> $warnings */
        $warnings = $this->data['directive_warnings'] ?? [];

        return $warnings;
    }

    /**
     * @param list<string> $groupNames
     *
     * @return list<array{directive: string, group: string, message: string}>
     */
    private function detectDirectiveConflicts(array $groupNames): array
    {
        $policies = $this->csp->getPolicies($groupNames);

        /** @var list<array{directive: string, group: string, message: string}> $warnings */
        $warnings = [];

        foreach ($policies as $groupName => $policy) {
            foreach ($policy->getPolicies() as $directive => $sources) {
                // Check 'none' mixed with other sources
                if (\in_array("'none'", $sources, true) && \count($sources) > 1) {
                    $warnings[] = [
                        'directive' => $directive,
                        'group' => $groupName,
                        'message' => "'none' has no effect when combined with other sources.",
                    ];
                }

                // Check strict-dynamic with host sources
                if (\in_array("'strict-dynamic'", $sources, true)) {
                    foreach ($sources as $source) {
                        if (!\str_starts_with($source, "'") && $source !== '*') {
                            $warnings[] = [
                                'directive' => $directive,
                                'group' => $groupName,
                                'message' => 'Host sources are ignored when \'strict-dynamic\' is present.',
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $warnings;
    }

    /** @return array<string, string> */
    private function parseHeader(string $header): array
    {
        if ($header === '') {
            return [];
        }

        $directives = [];
        foreach (\explode(';', $header) as $part) {
            $part = \trim($part);
            if ($part === '') {
                continue;
            }
            $tokens = \explode(' ', $part, 2);
            $directives[$tokens[0]] = $tokens[1] ?? '';
        }

        return $directives;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
