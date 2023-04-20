<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests;

use Aubes\CSPBundle\CSPPolicy;
use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Aubes\CSPBundle\CSPPolicy
 */
class CSPPolicyTest extends TestCase
{
    use ProphecyTrait;

    public function testPolicy()
    {
        $policy = new CSPPolicy(null, [], false, false);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever', $policy->render());
    }

    public function testOptions()
    {
        $policy = new CSPPolicy(null, [], false, false);

        $this->assertNull($policy->getReportTo());
        $this->assertFalse($policy->isBCSupport());
        $this->assertFalse($policy->isReportOnly());

        $reportTo = $this->prophesize(ReportTo::class);
        $policy = new CSPPolicy($reportTo->reveal(), [], true, true);

        $this->assertInstanceOf(ReportTo::class, $policy->getReportTo());
        $this->assertTrue($policy->isBCSupport());
        $this->assertTrue($policy->isReportOnly());
    }

    public function testInternalSource()
    {
        $policy = new CSPPolicy(null, [], false, false);

        $policy->addPolicy('script-src', 'self');

        $this->assertSame('script-src \'self\'', $policy->render());
    }

    public function testPolicyConstructor()
    {
        $directives = [
            'script-src' => ['whatever'],
        ];

        $policy = new CSPPolicy(null, $directives, false, false);

        $this->assertSame('script-src whatever', $policy->render());
    }

    public function testUnknownDirectiveConstructor()
    {
        $directives = [
            'unknown' => ['whatever'],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown directive unknown');

        new CSPPolicy(null, $directives, false, false);
    }

    public function testUnknownDirective()
    {
        $policy = new CSPPolicy(null, [], false, false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown directive unknown');

        $policy->addPolicy('unknown', 'whatever');
    }

    public function testWithReport()
    {
        $reportTo = $this->prophesize(ReportTo::class);
        $reportTo->getGroupName()->willReturn('group');
        $reportTo->getUrlEndpoints(Argument::any())->willReturn(['url']);
        $policy = new CSPPolicy($reportTo->reveal(), [], false, false);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever; report-to group', $policy->render());
    }

    public function testWithReportBCSupport()
    {
        $reportTo = $this->prophesize(ReportTo::class);
        $reportTo->getGroupName()->willReturn('group');
        $reportTo->getUrlEndpoints(Argument::any())->willReturn(['url']);
        $policy = new CSPPolicy($reportTo->reveal(), [], false, true);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever; report-to group; report-uri url', $policy->render());
    }
}
