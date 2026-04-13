<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Report;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ReportTo
{
    private const GROUP_NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * @param list<string> $endpoints
     */
    public function __construct(
        private RouterInterface $router,
        private string $groupName,
        private int $maxAge,
        private array $endpoints,
    ) {
        if (!\preg_match(self::GROUP_NAME_PATTERN, $groupName)) {
            throw new \InvalidArgumentException(\sprintf('Invalid reporting group name "%s": must contain only alphanumeric characters, hyphens and underscores', $groupName));
        }
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * @return list<string>
     */
    public function getUrlEndpoints(bool $absoluteUrl = true): array
    {
        $urls = [];

        foreach ($this->endpoints as $endpoint) {
            $urls[] = $this->router->generate($endpoint, ['group' => $this->groupName], $absoluteUrl ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH);
        }

        return $urls;
    }

    /**
     * @return array{group: string, max_age: int, endpoints: list<array{url: string}>}
     */
    public function renderReportTo(): array
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

    public function renderReportingEndpoints(): string
    {
        $urls = $this->getUrlEndpoints();

        if ($urls === []) {
            return '';
        }

        return $this->getGroupName() . '="' . $urls[0] . '"';
    }
}
