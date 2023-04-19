<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

class CSP
{
    protected array $policies = [];
    protected string $defaultGroup;
    protected bool $autoDefault;
    protected bool $enabled = true;

    public function __construct(array $groups, string $defaultGroup, bool $autoDefault)
    {
        if (!isset($groups[$defaultGroup])) {
            throw new \InvalidArgumentException('Unknown group for default group');
        }

        foreach ($groups as $groupName => $policy) {
            $this->addPolicy($policy, $groupName);
        }

        $this->defaultGroup = $defaultGroup;
        $this->autoDefault = $autoDefault;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function addPolicy(CSPPolicy $policy, string $groupName = null): void
    {
        $this->policies[$groupName ?? $this->defaultGroup] = $policy;
    }

    public function addDirective(string $directive, string $value, string $groupName = null): void
    {
        $this->policies[$groupName ?? $this->defaultGroup]->addPolicy($directive, $value);
    }

    /**
     * @return array<CSPPolicy>
     */
    public function getPolicies(array $groupNames = []): array
    {
        if (empty($groupNames) && $this->autoDefault) {
            $groupNames = [$this->defaultGroup];
        }

        return \array_intersect_key($this->policies, \array_flip($groupNames));
    }
}
