<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Model\CSPPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CSP::class)]
class CSPTest extends TestCase
{
    public function testHasGroup(): void
    {
        $csp = new CSP(['group' => $this->createMock(CSPPolicy::class)], 'group', false);

        $this->assertTrue($csp->hasGroup('group'));
        $this->assertFalse($csp->hasGroup('unknown'));
    }

    public function testGetPolicies(): void
    {
        $csp = new CSP(['group' => $this->createMock(CSPPolicy::class)], 'group', false);

        $this->assertEmpty($csp->getPolicies());
        $this->assertArrayHasKey('group', $csp->getPolicies(['group']));
        $this->assertEmpty($csp->getPolicies(['unknown']));
    }

    public function testEnabled(): void
    {
        $csp = new CSP(['group' => $this->createMock(CSPPolicy::class)], 'group', false);

        $this->assertTrue($csp->isEnabled());

        $csp->setEnabled(false);
        $this->assertFalse($csp->isEnabled());

        $csp->setEnabled(true);
        $this->assertTrue($csp->isEnabled());
    }

    public function testAddDirective(): void
    {
        $cspPolicy = $this->createMock(CSPPolicy::class);
        $csp = new CSP(['group' => $cspPolicy], 'group', false);

        $cspPolicy->expects($this->once())
            ->method('addPolicy')
            ->with('script-src', 'self');
        $csp->addDirective('script-src', 'self');
    }

    public function testAddDirectiveToNamedGroup(): void
    {
        $cspPolicy = $this->createMock(CSPPolicy::class);

        $csp = new CSP(['group' => $cspPolicy], 'group', false);

        $cspPolicy->expects($this->once())
            ->method('addPolicy')
            ->with('style-src', 'self');
        $csp->addDirective('style-src', 'self', 'group');
    }

    public function testAutoDefault(): void
    {
        $cspPolicy = $this->createMock(CSPPolicy::class);

        $csp = new CSP(['group' => $cspPolicy], 'group', true);

        $this->assertArrayHasKey('group', $csp->getPolicies());
        $this->assertArrayHasKey('group', $csp->getPolicies(['group']));
        $this->assertEmpty($csp->getPolicies(['unknown']));
    }

    public function testUnknownDefault(): void
    {
        $cspPolicy = $this->createMock(CSPPolicy::class);

        $this->expectException(\InvalidArgumentException::class);

        new CSP(['group' => $cspPolicy], 'unknown', false);
    }

    public function testReset(): void
    {
        $cspPolicy = new CSPPolicy(null, ['script-src' => ['self']], false, false);

        $csp = new CSP(['group' => $cspPolicy], 'group', false);

        $csp->addDirective('script-src', 'unsafe-inline', 'group');
        $csp->setEnabled(false);

        $csp->reset();

        $this->assertTrue($csp->isEnabled());

        $policies = $csp->getPolicies(['group']);
        $this->assertSame("script-src 'self'", $policies['group']->render());
    }
}
