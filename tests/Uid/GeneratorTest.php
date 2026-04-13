<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Uid;

use Aubes\CSPBundle\Uid\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Generator::class)]
class GeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $generator = new Generator();

        $result = $generator->generate(16);
        $this->assertSame(16, \strlen($result));
    }

    public function testGenerateNegativeLength(): void
    {
        $generator = new Generator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be 1 or greater');

        $generator->generate(0);
    }
}
