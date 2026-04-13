<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

use Aubes\CSPBundle\Model\CSPPolicy;
use Symfony\Contracts\Service\ResetInterface;

class CSP implements ResetInterface
{
    /** @var array<string, CSPPolicy> */
    private array $policies = [];

    /** @var array<string, CSPPolicy> */
    private array $initialPolicies = [];

    private bool $enabled = true;

    /**
     * @param array<string, CSPPolicy> $groups
     */
    public function __construct(
        array $groups,
        private readonly string $defaultGroup,
        private readonly bool $autoDefault,
    ) {
        if (!isset($groups[$defaultGroup])) {
            throw new \InvalidArgumentException('Unknown group for default group');
        }

        foreach ($groups as $groupName => $policy) {
            $this->addGroup($policy, $groupName);
        }

        foreach ($this->policies as $groupName => $policy) {
            $this->initialPolicies[$groupName] = clone $policy;
        }
    }

    public function hasGroup(string $groupName): bool
    {
        return \array_key_exists($groupName, $this->policies);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function addGroup(CSPPolicy $policy, ?string $groupName = null): void
    {
        $name = $groupName ?? $this->defaultGroup;

        if (isset($this->policies[$name])) {
            throw new \InvalidArgumentException(\sprintf('CSP group "%s" already exists', $name));
        }

        $this->policies[$name] = $policy;
    }

    public function addDirective(string $directive, string $value, ?string $groupName = null): void
    {
        $name = $groupName ?? $this->defaultGroup;

        if (!$this->hasGroup($name)) {
            throw new \InvalidArgumentException(\sprintf('Unknown CSP group "%s"', $name));
        }

        $this->policies[$name]->addPolicy($directive, $value);
    }

    /**
     * @return array<string, CSPPolicy>
     */
    public function getGroups(): array
    {
        return $this->policies;
    }

    /**
     * @param list<string> $groupNames
     *
     * @return array<string, CSPPolicy>
     */
    public function getPolicies(array $groupNames = []): array
    {
        if (empty($groupNames) && $this->autoDefault) {
            $groupNames = [$this->defaultGroup];
        }

        return \array_intersect_key($this->policies, \array_flip($groupNames));
    }

    public function reset(): void
    {
        $this->policies = [];
        foreach ($this->initialPolicies as $groupName => $policy) {
            $this->policies[$groupName] = clone $policy;
        }
        $this->enabled = true;
    }
}
