<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Tests\Uid;

use Aubes\CSPBundle\Uid\Generator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aubes\CSPBundle\Uid\Generator
 */
class GeneratorTest extends TestCase
{
    public function testGenerate()
    {
        $generator = new Generator();

        $this->assertSame(16, \mb_strlen($generator->generate(8)));
    }

    public function testGenerateNegativeLength()
    {
        $generator = new Generator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be 1 or greater');

        $generator->generate(0);
    }
}
