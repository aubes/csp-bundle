<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Twig;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Twig\CSPExtension;
use Aubes\CSPBundle\Uid\GeneratorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CSPExtension::class)]
class CSPExtensionTest extends TestCase
{
    /** @param list<string> $cspGroups */
    private function createRequestStack(array $cspGroups = []): RequestStack
    {
        $requestStack = new RequestStack();
        $request = new Request();

        if ($cspGroups !== []) {
            $request->attributes->set('_csp_groups', $cspGroups);
        }

        $requestStack->push($request);

        return $requestStack;
    }

    public function testNonceGenerated(): void
    {
        $rawBytes = \random_bytes(16);
        $expectedNonce = \base64_encode($rawBytes);

        $generator = $this->createMock(GeneratorInterface::class);
        $generator->expects($this->once())->method('generate')->with(16)->willReturn($rawBytes);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('script-src', "'nonce-{$expectedNonce}'", null);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $result = $extension->nonce('script-src');
        $this->assertSame('nonce="' . $expectedNonce . '"', $result);
    }

    public function testNonceCustom(): void
    {
        $generator = $this->createStub(GeneratorInterface::class);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('script-src', "'nonce-custom123'", 'group');

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $result = $extension->nonce('script-src', 'group', 'custom123');
        $this->assertSame('nonce="custom123"', $result);
    }

    public function testScriptNonce(): void
    {
        $rawBytes = \random_bytes(16);
        $expectedNonce = \base64_encode($rawBytes);

        $generator = $this->createStub(GeneratorInterface::class);
        $generator->method('generate')->willReturn($rawBytes);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('script-src', "'nonce-{$expectedNonce}'", null);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $this->assertSame('nonce="' . $expectedNonce . '"', $extension->scriptNonce());
    }

    public function testStyleNonce(): void
    {
        $rawBytes = \random_bytes(16);
        $expectedNonce = \base64_encode($rawBytes);

        $generator = $this->createStub(GeneratorInterface::class);
        $generator->method('generate')->willReturn($rawBytes);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('style-src', "'nonce-{$expectedNonce}'", null);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $this->assertSame('nonce="' . $expectedNonce . '"', $extension->styleNonce());
    }

    public function testHash(): void
    {
        $content = 'alert("hello")';
        $expectedHash = \base64_encode(\hash('sha256', $content, true));

        $generator = $this->createStub(GeneratorInterface::class);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('script-src', "'sha256-{$expectedHash}'", null);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());
        $extension->hash('script-src', $content);
    }

    public function testHashSha384(): void
    {
        $content = 'body { color: red }';
        $expectedHash = \base64_encode(\hash('sha384', $content, true));

        $generator = $this->createStub(GeneratorInterface::class);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('style-src', "'sha384-{$expectedHash}'", 'mygroup');

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());
        $extension->hash('style-src', $content, 'sha384', 'mygroup');
    }

    public function testHashInvalidAlgorithm(): void
    {
        $generator = $this->createStub(GeneratorInterface::class);
        $csp = $this->createStub(CSP::class);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported hash algorithm "md5"');

        $extension->hash('script-src', 'content', 'md5');
    }

    public function testNonceInvalidFormat(): void
    {
        $generator = $this->createStub(GeneratorInterface::class);
        $csp = $this->createStub(CSP::class);

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid nonce value');

        $extension->nonce('script-src', null, '" onload="alert(1)');
    }

    public function testNonceValidBase64Accepted(): void
    {
        $generator = $this->createStub(GeneratorInterface::class);
        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())->method('addDirective');

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack());

        $result = $extension->nonce('script-src', null, 'YWJjZGVm+/==');
        $this->assertSame('nonce="YWJjZGVm+/=="', $result);
    }

    public function testNonceAddedToAllActiveGroups(): void
    {
        $rawBytes = \random_bytes(16);
        $expectedNonce = \base64_encode($rawBytes);

        $generator = $this->createStub(GeneratorInterface::class);
        $generator->method('generate')->willReturn($rawBytes);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->exactly(2))
            ->method('addDirective')
            ->willReturnCallback(static function (string $directive, string $value, ?string $group) use ($expectedNonce): void {
                /** @var int $call */
                static $call = 0;
                ++$call;

                match ($call) {
                    1 => self::assertSame('group_a', $group),
                    2 => self::assertSame('group_b', $group),
                    default => self::fail('Unexpected call'),
                };

                self::assertSame('script-src', $directive);
                self::assertSame("'nonce-{$expectedNonce}'", $value);
            });

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack(['group_a', 'group_b']));

        $extension->nonce('script-src');
    }

    public function testNonceExplicitGroupIgnoresRequestGroups(): void
    {
        $rawBytes = \random_bytes(16);

        $generator = $this->createStub(GeneratorInterface::class);
        $generator->method('generate')->willReturn($rawBytes);

        $csp = $this->createMock(CSP::class);
        $csp->expects($this->once())
            ->method('addDirective')
            ->with('script-src', $this->anything(), 'explicit');

        $extension = new CSPExtension($csp, $generator, $this->createRequestStack(['group_a', 'group_b']));

        $extension->nonce('script-src', 'explicit');
    }
}
