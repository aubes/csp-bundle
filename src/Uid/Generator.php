<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Uid;

class Generator implements GeneratorInterface
{
    public function generate(int $length): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be 1 or greater');
        }

        return \bin2hex(\random_bytes($length));
    }
}
