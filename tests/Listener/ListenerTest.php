<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Listener;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Listener\CSPListener;
use Aubes\CSPBundle\Model\CSPPolicy;
use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(CSPListener::class)]
class ListenerTest extends TestCase
{
    public function testListener(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, true, false, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertEquals('script-src \'self\'', $event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testReportOnly(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, true, true, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    public function testWithReportModern(): void
    {
        $report = $this->createStub(ReportTo::class);
        $report->method('renderReportingEndpoints')->willReturn('group_test="https://example.com/report"');
        $report->method('getGroupName')->willReturn('group_test');

        $csp = $this->mockCsp(['script-src' => ['self']], $report, true, false, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertTrue($event->getResponse()->headers->has('Reporting-Endpoints'));
        $this->assertFalse($event->getResponse()->headers->has('Report-To'));
        $this->assertEquals('group_test="https://example.com/report"', $event->getResponse()->headers->get('Reporting-Endpoints'));
    }

    public function testWithReportBCSupport(): void
    {
        $report = $this->createStub(ReportTo::class);
        $report->method('renderReportingEndpoints')->willReturn('group_test="https://example.com/report"');
        $report->method('renderReportTo')->willReturn(['group' => 'group_test', 'max_age' => 3600, 'endpoints' => [['url' => 'https://example.com/report']]]);
        $report->method('getGroupName')->willReturn('group_test');
        $report->method('getUrlEndpoints')->willReturn(['/report']);

        $csp = $this->mockCsp(['script-src' => ['self']], $report, true, false, true);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertTrue($event->getResponse()->headers->has('Reporting-Endpoints'));
        $this->assertTrue($event->getResponse()->headers->has('Report-To'));
    }

    public function testOnReportRoute(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, true, false, false);
        $event = $this->createResponseEvent(['_route' => 'csp-route', '_csp_groups' => []]);

        $listener = new CSPListener($csp, ['csp-route']);
        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    public function testCspDisabled(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, false, false, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    public function testCspDisabledByRouteAttribute(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, true, false, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => [], '_csp_disabled' => true]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    public function testMultiGroupSameModeThrowsException(): void
    {
        $csp = $this->createStub(CSP::class);
        $csp->method('isEnabled')->willReturn(true);
        $csp->method('getPolicies')->willReturn([
            new CSPPolicy(null, ['script-src' => ['\'self\'']], false, false),
            new CSPPolicy(null, ['script-src' => ['\'self\'', '\'unsafe-inline\'']], false, false),
        ]);

        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => ['a', 'b']]);

        $listener = new CSPListener($csp, []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple groups resolve to the same header');
        $listener->onKernelResponse($event);
    }

    public function testMultiGroupDifferentModeSeparateHeaders(): void
    {
        $csp = $this->createStub(CSP::class);
        $csp->method('isEnabled')->willReturn(true);
        $csp->method('getPolicies')->willReturn([
            new CSPPolicy(null, ['script-src' => ['\'self\'']], false, false),
            new CSPPolicy(null, ['script-src' => ['\'self\'', '\'unsafe-inline\'']], true, false),
        ]);

        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => ['a', 'b']]);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertEquals('script-src \'self\'', $event->getResponse()->headers->get('Content-Security-Policy'));
        $this->assertEquals('script-src \'self\' \'unsafe-inline\'', $event->getResponse()->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function testNotMainRequest(): void
    {
        $csp = $this->mockCsp(['script-src' => ['self']], null, true, false, false);
        $event = $this->createResponseEvent(['_route' => 'whatever', '_csp_groups' => []], HttpKernelInterface::SUB_REQUEST);

        $listener = new CSPListener($csp, []);
        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    /**
     * @param array<string, mixed> $requestAttributes
     */
    private function createResponseEvent(array $requestAttributes, int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->add($requestAttributes);

        return new ResponseEvent($kernel, $request, $requestType, new Response());
    }

    /**
     * @param array<string, list<string>> $policies
     */
    private function mockCsp(array $policies, ?ReportTo $reportTo, bool $enabled, bool $reportOnly, bool $bcSupport): CSP
    {
        $csp = $this->createStub(CSP::class);
        $csp->method('isEnabled')->willReturn($enabled);
        $csp->method('getPolicies')->willReturn([
            new CSPPolicy($reportTo, $policies, $reportOnly, $bcSupport),
        ]);

        return $csp;
    }
}
