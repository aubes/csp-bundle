<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Event;

use Aubes\CSPBundle\Model\CSPPolicy;
use Symfony\Component\HttpFoundation\Request;

final class CSPHeaderEvent
{
    /**
     * @param array<string, CSPPolicy> $policies
     */
    public function __construct(
        public readonly Request $request,
        public readonly array $policies,
    ) {
    }
}
