<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Report;

use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ReportTo::class)]
class ReportToTest extends TestCase
{
    public function testReportTo(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $params, int $referenceType): string {
                /** @var string $group */
                $group = $params['group'];

                return match ($referenceType) {
                    UrlGeneratorInterface::ABSOLUTE_URL => 'https://example.com/csp-report/' . $group,
                    UrlGeneratorInterface::ABSOLUTE_PATH => '/csp-report/' . $group,
                    default => throw new \LogicException('Unexpected reference type'),
                };
            }
        );

        $report = new ReportTo($router, 'group', 100, ['csp_report']);

        $this->assertSame(['https://example.com/csp-report/group'], $report->getUrlEndpoints());
        $this->assertSame(['/csp-report/group'], $report->getUrlEndpoints(false));
        $this->assertSame('group', $report->getGroupName());
        $this->assertSame(100, $report->getMaxAge());
    }

    public function testRenderReportTo(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://example.com/csp-report');

        $report = new ReportTo($router, 'group', 3600, ['csp_report']);

        $rendered = $report->renderReportTo();
        $this->assertSame('group', $rendered['group']);
        $this->assertSame(3600, $rendered['max_age']);
        $this->assertCount(1, $rendered['endpoints']);
        $this->assertSame('https://example.com/csp-report', $rendered['endpoints'][0]['url']);
    }

    public function testRenderReportingEndpoints(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://example.com/csp-report');

        $report = new ReportTo($router, 'group', 3600, ['csp_report']);

        $this->assertSame('group="https://example.com/csp-report"', $report->renderReportingEndpoints());
    }
}
