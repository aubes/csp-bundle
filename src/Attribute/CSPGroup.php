<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CSPGroup
{
    public function __construct(
        public readonly string $group,
    ) {
    }
}
