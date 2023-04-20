<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Twig;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\CSPPolicy;
use Aubes\CSPBundle\Report\ReportTo;
use Aubes\CSPBundle\Twig\CSPExtension;
use Aubes\CSPBundle\Uid\GeneratorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Aubes\CSPBundle\Twig\CSPExtension
 */
class CSPExtensionTest extends TestCase
{
    use ProphecyTrait;

    public function testNonce()
    {
        $policy = $this->mockPolicy();

        $csp = $this->mockCsp([$policy->reveal()], null, true, false, false);
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact(null))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-123456\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-123456\''), Argument::exact(null))->shouldBeCalledOnce();

        $generator = $this->mockGenerator(8, '12345678');

        $extension = new CSPExtension($csp->reveal(), $generator->reveal());

        $extension->nonce('script-src');
        $extension->nonce('script-src', 'group');
        $extension->nonce('script-src', 'group', '123456');
        $extension->nonce('script-src', null, '123456');
    }

    public function testScriptNonce()
    {
        $policy = $this->mockPolicy();

        $csp = $this->mockCsp([$policy->reveal()], null, true, false, false);
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact(null))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-123456\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('script-src'), Argument::exact('\'nonce-123456\''), Argument::exact(null))->shouldBeCalledOnce();

        $generator = $this->mockGenerator(8, '12345678');

        $extension = new CSPExtension($csp->reveal(), $generator->reveal());

        $extension->scriptNonce();
        $extension->scriptNonce('group');
        $extension->scriptNonce('group', '123456');
        $extension->scriptNonce(null, '123456');
    }

    public function testStyleNonce()
    {
        $policy = $this->mockPolicy();

        $csp = $this->mockCsp([$policy->reveal()], null, true, false, false);
        $csp->addDirective(Argument::exact('style-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact(null))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('style-src'), Argument::exact('\'nonce-MTIzNDU2Nzg=\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('style-src'), Argument::exact('\'nonce-123456\''), Argument::exact('group'))->shouldBeCalledOnce();
        $csp->addDirective(Argument::exact('style-src'), Argument::exact('\'nonce-123456\''), Argument::exact(null))->shouldBeCalledOnce();

        $generator = $this->mockGenerator(8, '12345678');

        $extension = new CSPExtension($csp->reveal(), $generator->reveal());

        $extension->styleNonce();
        $extension->styleNonce('group');
        $extension->styleNonce('group', '123456');
        $extension->styleNonce(null, '123456');
    }

    protected function mockPolicy()
    {
        $policy = $this->prophesize(CSPPolicy::class);

        return $policy;
    }

    protected function mockCsp(array $policies, ?ReportTo $reportTo, bool $enabled, bool $reportOnly, bool $bcSupport)
    {
        $csp = $this->prophesize(CSP::class);
        $csp->isEnabled()->willReturn($enabled);
        $csp->getPolicies(Argument::any())->willReturn($policies);

        return $csp;
    }

    protected function mockGenerator(int $length, string $return)
    {
        $generator = $this->prophesize(GeneratorInterface::class);
        $generator->generate(Argument::exact($length))->willReturn($return);

        return $generator;
    }
}
