<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests;

use Aubes\CSPBundle\Model\CSPPolicy;
use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CSPPolicy::class)]
class CSPPolicyTest extends TestCase
{
    public function testPolicy(): void
    {
        $policy = new CSPPolicy(null, [], false, false);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever', $policy->render());
    }

    public function testOptions(): void
    {
        $policy = new CSPPolicy(null, [], false, false);

        $this->assertNull($policy->getReportTo());
        $this->assertFalse($policy->isBCSupport());
        $this->assertFalse($policy->isReportOnly());

        $reportTo = $this->createMock(ReportTo::class);
        $policy = new CSPPolicy($reportTo, [], true, true);

        $this->assertInstanceOf(ReportTo::class, $policy->getReportTo());
        $this->assertTrue($policy->isBCSupport());
        $this->assertTrue($policy->isReportOnly());
    }

    public function testInternalSource(): void
    {
        $policy = new CSPPolicy(null, [], false, false);

        $policy->addPolicy('script-src', 'self');

        $this->assertSame('script-src \'self\'', $policy->render());
    }

    public function testPolicyConstructor(): void
    {
        $directives = [
            'script-src' => ['whatever'],
        ];

        $policy = new CSPPolicy(null, $directives, false, false);

        $this->assertSame('script-src whatever', $policy->render());
    }

    public function testUnknownDirectiveConstructor(): void
    {
        $directives = [
            'unknown' => ['whatever'],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown directive unknown');

        new CSPPolicy(null, $directives, false, false);
    }

    public function testUnknownDirective(): void
    {
        $policy = new CSPPolicy(null, [], false, false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown directive unknown');

        $policy->addPolicy('unknown', 'whatever');
    }

    public function testWithReport(): void
    {
        $reportTo = $this->createStub(ReportTo::class);
        $reportTo->method('getGroupName')->willReturn('group');
        $reportTo->method('getUrlEndpoints')->willReturn(['url']);

        $policy = new CSPPolicy($reportTo, [], false, false);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever; report-to group', $policy->render());
    }

    public function testWithReportBCSupport(): void
    {
        $reportTo = $this->createStub(ReportTo::class);
        $reportTo->method('getGroupName')->willReturn('group');
        $reportTo->method('getUrlEndpoints')->willReturn(['url']);

        $policy = new CSPPolicy($reportTo, [], false, true);

        $policy->addPolicy('script-src', 'whatever');

        $this->assertSame('script-src whatever; report-to group; report-uri url', $policy->render());
    }
}
