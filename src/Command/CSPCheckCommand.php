<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Command;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Model\CSPPolicy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'csp:check',
    description: 'Audit CSP configuration for security issues and best practice violations',
)]
class CSPCheckCommand extends Command
{
    /** @var list<array{level: string, group: string, directive: string, message: string}> */
    private array $findings = [];

    public function __construct(
        private readonly CSP $csp,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('CSP Configuration Audit');

        $this->findings = [];

        /** @var array<string, CSPPolicy> $policies */
        $policies = $this->getAllPolicies();

        if (empty($policies)) {
            $io->warning('No CSP groups found.');

            return Command::FAILURE;
        }

        foreach ($policies as $groupName => $policy) {
            $rendered = $policy->render();
            $directives = $this->parseRenderedPolicy($rendered);

            $this->checkMissingDefaultSrc($groupName, $directives);
            $this->checkMissingScriptSrc($groupName, $directives);
            $this->checkMissingObjectSrc($groupName, $directives);
            $this->checkMissingBaseUri($groupName, $directives);
            $this->checkUnsafeInline($groupName, $directives);
            $this->checkUnsafeEval($groupName, $directives);
            $this->checkWildcards($groupName, $directives);
            $this->checkPlainUrlSchemes($groupName, $directives);
            $this->checkHttpSources($groupName, $directives);
            $this->checkIpSources($groupName, $directives);
            $this->checkNonceWithUnsafeInline($groupName, $directives);
            $this->checkMissingFrameAncestors($groupName, $directives);
            $this->checkMissingFormAction($groupName, $directives);
            $this->checkMissingReporting($groupName, $policy);
            $this->checkStrictDynamicWithoutNonce($groupName, $directives);
            $this->checkTrustedTypesWithoutRequire($groupName, $directives);
            $this->checkNoneWithOtherSources($groupName, $directives);
            $this->checkStrictDynamicWithIgnoredSources($groupName, $directives);
        }

        if (empty($this->findings)) {
            $io->success('No issues found. CSP configuration looks solid.');

            return Command::SUCCESS;
        }

        $this->renderFindings($io);

        $errors = \array_filter($this->findings, static fn (array $f) => $f['level'] === 'error');

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<string, CSPPolicy>
     */
    private function getAllPolicies(): array
    {
        return $this->csp->getGroups();
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseRenderedPolicy(string $rendered): array
    {
        $directives = [];

        foreach (\explode('; ', $rendered) as $part) {
            $tokens = \explode(' ', $part, 2);
            $directive = $tokens[0];
            $values = isset($tokens[1]) ? \explode(' ', $tokens[1]) : [];
            $directives[$directive] = $values;
        }

        return $directives;
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingDefaultSrc(string $group, array $directives): void
    {
        if (!isset($directives['default-src'])) {
            $this->finding('warning', $group, 'default-src', 'Missing default-src directive. Unspecified fetch directives will have no restrictions.');
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingScriptSrc(string $group, array $directives): void
    {
        if (!isset($directives['script-src']) && !isset($directives['default-src'])) {
            $this->finding('error', $group, 'script-src', 'No script-src or default-src defined. Scripts are unrestricted.');
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingObjectSrc(string $group, array $directives): void
    {
        $sources = $directives['object-src'] ?? $directives['default-src'] ?? null;

        if ($sources === null) {
            $this->finding('error', $group, 'object-src', "Missing object-src allows plugin injection. Add object-src 'none'.");
        } elseif (!\in_array("'none'", $sources, true)) {
            $this->finding('warning', $group, 'object-src', "object-src should be 'none' unless plugins are explicitly needed.");
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingBaseUri(string $group, array $directives): void
    {
        if (!isset($directives['base-uri'])) {
            $hasNonce = $this->hasNonceOrHash($directives['script-src'] ?? []);

            if ($hasNonce || (isset($directives['script-src']) && \in_array("'strict-dynamic'", $directives['script-src'], true))) {
                $this->finding('error', $group, 'base-uri', 'Missing base-uri while using nonces/hashes. This allows base tag injection to bypass CSP.');
            } else {
                $this->finding('warning', $group, 'base-uri', "Missing base-uri directive. Consider adding base-uri 'none' or 'self'.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkUnsafeInline(string $group, array $directives): void
    {
        foreach (['script-src', 'script-src-elem', 'script-src-attr'] as $directive) {
            if (isset($directives[$directive]) && \in_array("'unsafe-inline'", $directives[$directive], true)) {
                if (!$this->hasNonceOrHash($directives[$directive])) {
                    $this->finding('error', $group, $directive, "'unsafe-inline' allows execution of arbitrary inline scripts.");
                }
            }
        }

        foreach (['style-src', 'style-src-elem', 'style-src-attr'] as $directive) {
            if (isset($directives[$directive]) && \in_array("'unsafe-inline'", $directives[$directive], true)) {
                $this->finding('info', $group, $directive, "'unsafe-inline' on styles is lower risk but consider nonces/hashes instead.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkUnsafeEval(string $group, array $directives): void
    {
        foreach (['script-src', 'default-src'] as $directive) {
            if (isset($directives[$directive]) && \in_array("'unsafe-eval'", $directives[$directive], true)) {
                $hasTrustedTypes = isset($directives['require-trusted-types-for']);
                $message = "'unsafe-eval' allows eval() and similar DOM APIs.";
                if (!$hasTrustedTypes) {
                    $message .= " Consider adding require-trusted-types-for 'script' for XSS mitigation.";
                }
                $this->finding('warning', $group, $directive, $message);
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkWildcards(string $group, array $directives): void
    {
        $sensitiveDirectives = ['script-src', 'script-src-elem', 'object-src', 'default-src'];

        foreach ($sensitiveDirectives as $directive) {
            if (isset($directives[$directive]) && \in_array('*', $directives[$directive], true)) {
                $this->finding('error', $group, $directive, "Wildcard '*' effectively disables CSP for this directive.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkPlainUrlSchemes(string $group, array $directives): void
    {
        $sensitiveDirectives = ['script-src', 'script-src-elem', 'object-src', 'default-src'];
        $dangerousSchemes = ['data:', 'blob:', 'filesystem:'];

        foreach ($sensitiveDirectives as $directive) {
            if (!isset($directives[$directive])) {
                continue;
            }

            foreach ($directives[$directive] as $source) {
                if (\in_array($source, $dangerousSchemes, true)) {
                    $this->finding('error', $group, $directive, "'{$source}' scheme allows arbitrary content execution.");
                }

                if ($source === 'https:' || $source === 'http:') {
                    $this->finding('warning', $group, $directive, "'{$source}' scheme allows loading from any {$source} origin.");
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkHttpSources(string $group, array $directives): void
    {
        foreach ($directives as $directive => $sources) {
            foreach ($sources as $source) {
                if (\str_starts_with($source, 'http://')) {
                    $this->finding('warning', $group, $directive, "Insecure HTTP source '{$source}'. Use HTTPS instead.");
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkIpSources(string $group, array $directives): void
    {
        foreach ($directives as $directive => $sources) {
            foreach ($sources as $source) {
                if (\preg_match('/^https?:\/\/(\d{1,3}\.){3}\d{1,3}/', $source) || \preg_match('/^https?:\/\/\[/', $source)) {
                    $this->finding('info', $group, $directive, "IP address source '{$source}'. Typically a development-only configuration.");
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkNonceWithUnsafeInline(string $group, array $directives): void
    {
        foreach (['script-src', 'script-src-elem', 'style-src', 'style-src-elem'] as $directive) {
            if (!isset($directives[$directive])) {
                continue;
            }

            $hasNonce = $this->hasNonceOrHash($directives[$directive]);
            $hasUnsafeInline = \in_array("'unsafe-inline'", $directives[$directive], true);

            if ($hasNonce && $hasUnsafeInline) {
                $this->finding('info', $group, $directive, "'unsafe-inline' is ignored when nonces/hashes are present (CSP Level 2+). It provides fallback for CSP Level 1 browsers only.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingFrameAncestors(string $group, array $directives): void
    {
        if (!isset($directives['frame-ancestors'])) {
            $this->finding('info', $group, 'frame-ancestors', "Missing frame-ancestors. Consider adding frame-ancestors 'self' to prevent clickjacking.");
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkMissingFormAction(string $group, array $directives): void
    {
        if (!isset($directives['form-action'])) {
            $this->finding('info', $group, 'form-action', 'Missing form-action. Forms can submit to any origin.');
        }
    }

    private function checkMissingReporting(string $group, CSPPolicy $policy): void
    {
        if ($policy->getReportTo() === null) {
            $this->finding('info', $group, 'report-to', 'No reporting endpoint configured. CSP violations will not be reported.');
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkStrictDynamicWithoutNonce(string $group, array $directives): void
    {
        foreach (['script-src', 'script-src-elem', 'default-src'] as $directive) {
            if (!isset($directives[$directive])) {
                continue;
            }

            if (\in_array("'strict-dynamic'", $directives[$directive], true) && !$this->hasNonceOrHash($directives[$directive])) {
                $this->finding('error', $group, $directive, "'strict-dynamic' without nonce or hash has no effect. Scripts will be blocked.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkTrustedTypesWithoutRequire(string $group, array $directives): void
    {
        if (isset($directives['trusted-types']) && !isset($directives['require-trusted-types-for'])) {
            $this->finding('warning', $group, 'trusted-types', "trusted-types is defined but require-trusted-types-for is missing. Trusted Types won't be enforced.");
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkNoneWithOtherSources(string $group, array $directives): void
    {
        foreach ($directives as $directive => $sources) {
            if (\in_array("'none'", $sources, true) && \count($sources) > 1) {
                $this->finding('error', $group, $directive, "'none' is mixed with other sources. 'none' means no content is allowed, additional sources are contradictory.");
            }
        }
    }

    /**
     * @param array<string, list<string>> $directives
     */
    private function checkStrictDynamicWithIgnoredSources(string $group, array $directives): void
    {
        foreach (['script-src', 'script-src-elem', 'default-src'] as $directive) {
            if (!isset($directives[$directive])) {
                continue;
            }

            $sources = $directives[$directive];

            if (!\in_array("'strict-dynamic'", $sources, true)) {
                continue;
            }

            $keywords = ["'self'", "'unsafe-inline'", "'strict-dynamic'", "'none'"];

            foreach ($sources as $source) {
                if (\in_array($source, $keywords, true)) {
                    continue;
                }

                if (\str_starts_with($source, "'nonce-") || \preg_match("/^'sha(256|384|512)-/", $source)) {
                    continue;
                }

                $this->finding('warning', $group, $directive, \sprintf("'%s' is ignored when 'strict-dynamic' is present. Only nonce/hash-loaded scripts are allowed.", $source));

                break;
            }
        }
    }

    /**
     * @param list<string> $sources
     */
    private function hasNonceOrHash(array $sources): bool
    {
        foreach ($sources as $source) {
            if (\str_starts_with($source, "'nonce-") || \preg_match("/^'sha(256|384|512)-/", $source)) {
                return true;
            }
        }

        return false;
    }

    private function finding(string $level, string $group, string $directive, string $message): void
    {
        $this->findings[] = [
            'level' => $level,
            'group' => $group,
            'directive' => $directive,
            'message' => $message,
        ];
    }

    private function renderFindings(SymfonyStyle $io): void
    {
        $icons = ['error' => 'ERROR', 'warning' => 'WARN', 'info' => 'INFO'];

        $errors = \array_filter($this->findings, static fn (array $f) => $f['level'] === 'error');
        $warnings = \array_filter($this->findings, static fn (array $f) => $f['level'] === 'warning');
        $infos = \array_filter($this->findings, static fn (array $f) => $f['level'] === 'info');

        $rows = [];
        foreach ($this->findings as $finding) {
            $rows[] = [
                $icons[$finding['level']],
                $finding['group'],
                $finding['directive'],
                $finding['message'],
            ];
        }

        $io->table(['Level', 'Group', 'Directive', 'Finding'], $rows);

        $io->writeln(\sprintf(
            'Found %d error(s), %d warning(s), %d info(s)',
            \count($errors),
            \count($warnings),
            \count($infos),
        ));
    }
}
