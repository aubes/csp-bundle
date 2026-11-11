<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests;

use Aubes\CSPBundle\Model\CSPPolicy;
use Aubes\CSPBundle\Preset\CSPPreset;
use Aubes\CSPBundle\Report\ReportTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testDuplicateSourceIsAddedOnce(): void
    {
        $policy = new CSPPolicy(null, ['script-src' => ['self', "'self'"]], false, false);

        $policy->addPolicy('script-src', 'self');
        $policy->addPolicy('script-src', 'https://cdn.example.com');
        $policy->addPolicy('script-src', 'https://cdn.example.com');

        $this->assertSame(['script-src' => ["'self'", 'https://cdn.example.com']], $policy->getPolicies());
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

    public function testValuelessDirectiveConstructor(): void
    {
        $directives = [
            'default-src' => ['self'],
            'upgrade-insecure-requests' => [],
        ];

        $policy = new CSPPolicy(null, $directives, false, false);

        $this->assertSame("default-src 'self'; upgrade-insecure-requests", $policy->render());
    }

    public function testValuelessSandboxConstructor(): void
    {
        $directives = [
            'default-src' => ['self'],
            'sandbox' => [],
        ];

        $policy = new CSPPolicy(null, $directives, false, false);

        $this->assertSame("default-src 'self'; sandbox", $policy->render());
    }

    public function testSandboxWithValues(): void
    {
        $policy = new CSPPolicy(null, ['sandbox' => ['allow-scripts', 'allow-forms']], false, false);

        $this->assertSame('sandbox allow-scripts allow-forms', $policy->render());
    }

    public function testEmptyDirectiveIsOmitted(): void
    {
        $directives = [
            'default-src' => ['self'],
            'script-src' => [],
        ];

        $policy = new CSPPolicy(null, $directives, false, false);

        $this->assertSame("default-src 'self'", $policy->render());
    }

    #[DataProvider('presetsWithUpgradeInsecureRequests')]
    public function testPresetRendersUpgradeInsecureRequests(CSPPreset $preset): void
    {
        $policy = new CSPPolicy(null, $preset->policies(), false, false);

        $this->assertStringEndsWith('; upgrade-insecure-requests', $policy->render());
    }

    /**
     * @return iterable<string, array{CSPPreset}>
     */
    public static function presetsWithUpgradeInsecureRequests(): iterable
    {
        yield 'strict' => [CSPPreset::Strict];
        yield 'permissive' => [CSPPreset::Permissive];
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
