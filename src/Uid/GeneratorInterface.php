<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Uid;

interface GeneratorInterface
{
    public function generate(int $length): string;
}
