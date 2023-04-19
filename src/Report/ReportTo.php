<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Report;

use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class ReportTo
{
    protected RouterInterface $router;
    protected string $groupName;
    protected int $maxAge;
    protected array $endpoints;

    public function __construct(RouterInterface $router, string $groupName, int $maxAge, array $endpoints)
    {
        $this->router = $router;
        $this->groupName = $groupName;
        $this->maxAge = $maxAge;
        $this->endpoints = $endpoints;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    public function getUrlEndpoints(bool $absoluteUrl = true): array
    {
        $urls = [];

        foreach ($this->endpoints as $endpoint) {
            $urls[] = $this->router->generate($endpoint, ['group' => $this->groupName], $absoluteUrl ? Router::ABSOLUTE_URL : Router::ABSOLUTE_PATH);
        }

        return $urls;
    }

    public function render(): array
    {
        $reportArray = [
            'group' => $this->getGroupName(),
            'max_age' => $this->getMaxAge(),
            'endpoints' => [],
        ];

        foreach ($this->getUrlEndpoints() as $endpoint) {
            $reportArray['endpoints'][] = [
                'url' => $endpoint,
            ];
        }

        return $reportArray;
    }
}
