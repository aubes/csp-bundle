<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\CSPPolicy;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class CSPTest extends TestCase
{
    use ProphecyTrait;

    public function testDefault()
    {
        $cspPolicy = $this->prophesize(CSPPolicy::class);

        $csp = new CSP(['group' => $cspPolicy->reveal()], 'group', false);

        $this->assertTrue($csp->hasGroup('group'));
        $this->assertFalse($csp->hasGroup('unknown'));

        $this->assertEmpty($csp->getPolicies());
        $this->assertArrayHasKey('group', $csp->getPolicies(['group']));
        $this->assertEmpty($csp->getPolicies(['unknown']));

        $this->assertTrue($csp->isEnabled());

        $csp->setEnabled(false);
        $this->assertFalse($csp->isEnabled());

        $csp->setEnabled(true);
        $this->assertTrue($csp->isEnabled());

        $cspPolicy->addPolicy(Argument::exact('script-src'), Argument::exact('self'))->shouldBeCalledOnce();
        $csp->addDirective('script-src', 'self');

        $cspPolicy->addPolicy(Argument::exact('style-src'), Argument::exact('self'))->shouldBeCalledOnce();
        $csp->addDirective('style-src', 'self', 'group');
    }

    public function testAutoDefault()
    {
        $cspPolicy = $this->prophesize(CSPPolicy::class);

        $csp = new CSP(['group' => $cspPolicy->reveal()], 'group', true);

        $this->assertArrayHasKey('group', $csp->getPolicies());
        $this->assertArrayHasKey('group', $csp->getPolicies(['group']));
        $this->assertEmpty($csp->getPolicies(['unknown']));
    }
}
