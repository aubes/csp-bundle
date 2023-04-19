<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Listener;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\CSPPolicy;
use Aubes\CSPBundle\Listener\CSPListener;
use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testListener()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $csp = $this->mockCsp($policies, null, true, false, false);

        $event = $this->createResponseEvent([
            '_route' => 'whatever',
            '_csp_groups' => [],
        ]);

        $listener = new CSPListener($csp->reveal(), []);

        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertEquals('script-src \'self\'', $event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testReportOnly()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $csp = $this->mockCsp($policies, null, true, true, false);

        $event = $this->createResponseEvent([
            '_route' => 'whatever',
            '_csp_groups' => [],
        ]);

        $listener = new CSPListener($csp->reveal(), []);

        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
    }

    public function testWithReport()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $report = $this->prophesize(ReportTo::class);
        $report->render()->willReturn(['rendered']);
        $report->getGroupName()->willReturn('group_test');

        $csp = $this->mockCsp($policies, $report->reveal(), true, false, false);

        $event = $this->createResponseEvent([
            '_route' => 'whatever',
            '_csp_groups' => [],
        ]);

        $listener = new CSPListener($csp->reveal(), []);

        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));

        $this->assertTrue($event->getResponse()->headers->has('Report-To'));
        $this->assertEquals('[["rendered"]]', $event->getResponse()->headers->get('Report-To'));
    }

    public function testOnReportRoute()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $csp = $this->mockCsp($policies, null, true, false, false);

        $event = $this->createResponseEvent([
            '_route' => 'csp-route',
            '_csp_groups' => [],
        ]);

        $listener = new CSPListener($csp->reveal(), ['csp-route']);

        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function testCspDisabled()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $csp = $this->mockCsp($policies, null, false, false, false);

        $event = $this->createResponseEvent([
            '_route' => 'whatever',
            '_csp_groups' => [],
        ]);

        $listener = new CSPListener($csp->reveal(), []);

        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function testNotMainRequest()
    {
        $policies = [
            'script-src' => [
                'self',
            ],
        ];

        $csp = $this->mockCsp($policies, null, true, false, false);

        $event = $this->createResponseEvent([
            '_route' => 'whatever',
            '_csp_groups' => [],
        ], HttpKernelInterface::SUB_REQUEST);

        $listener = new CSPListener($csp->reveal(), []);

        $listener->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy'));
        $this->assertFalse($event->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
    }

    protected function createResponseEvent(array $requestAttributes, int $requestType = HttpKernelInterface::MAIN_REQUEST)
    {
        $kernel = $this->prophesize(HttpKernelInterface::class);

        $response = $this->prophesize(Response::class);
        $response->headers = new ParameterBag([]);

        $request = $this->prophesize(Request::class);
        $request->attributes = new ParameterBag($requestAttributes);

        return new ResponseEvent($kernel->reveal(), $request->reveal(), $requestType, $response->reveal());
    }

    protected function mockCsp(array $policies, ?ReportTo $reportTo, bool $enabled, bool $reportOnly, bool $bcSupport)
    {
        $csp = $this->prophesize(CSP::class);
        $csp->isEnabled()->willReturn($enabled);
        $csp->getPolicies(Argument::any())->willReturn([
            new CSPPolicy($reportTo, $policies, $reportOnly, $bcSupport),
        ]);

        return $csp;
    }
}
