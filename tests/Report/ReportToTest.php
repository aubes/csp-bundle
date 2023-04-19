<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Report;

use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class ReportToTest extends TestCase
{
    use ProphecyTrait;

    public function testReportTo()
    {
        $router = $this->prophesize(RouterInterface::class);
        $router->generate(Argument::any(), Argument::any(), Argument::exact(Router::ABSOLUTE_URL))->willReturn('absolute-url');
        $router->generate(Argument::any(), Argument::any(), Argument::exact(Router::ABSOLUTE_PATH))->willReturn('absolute-path');

        $report = new ReportTo($router->reveal(), 'group', 100, ['csp_report']);

        $this->assertSame(['absolute-url'], $report->getUrlEndpoints());
        $this->assertSame(['absolute-path'], $report->getUrlEndpoints(false));
        $this->assertSame('group', $report->getGroupName());
        $this->assertSame(100, $report->getMaxAge());

        $rendered = $report->render();
        $this->assertArrayHasKey('group', $rendered);
        $this->assertArrayHasKey('max_age', $rendered);
        $this->assertArrayHasKey('endpoints', $rendered);
    }
}
