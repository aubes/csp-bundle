<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Listener;

use Aubes\CSPBundle\Attribute\CSPDisabled;
use Aubes\CSPBundle\Attribute\CSPGroup;
use Aubes\CSPBundle\CSP;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class CSPAttributeListener
{
    public function __construct(
        private readonly CSP $csp,
    ) {
    }

    #[AsEventListener]
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->getAttributes(CSPDisabled::class)) {
            $event->getRequest()->attributes->set('_csp_disabled', true);

            return;
        }

        /** @var list<CSPGroup> $groupAttributes */
        $groupAttributes = $event->getAttributes(CSPGroup::class);

        if ($groupAttributes !== []) {
            $groups = \array_map(static fn (CSPGroup $attr) => $attr->group, $groupAttributes);

            /** @var list<string> $existing */
            $existing = (array) $event->getRequest()->attributes->get('_csp_groups', []);
            $event->getRequest()->attributes->set('_csp_groups', \array_values(\array_unique([...$existing, ...$groups])));
        }

        /** @var list<string> $resolvedGroups */
        $resolvedGroups = (array) $event->getRequest()->attributes->get('_csp_groups', []);

        if ($resolvedGroups === []) {
            $defaultGroups = \array_keys($this->csp->getPolicies([]));

            if ($defaultGroups !== []) {
                $event->getRequest()->attributes->set('_csp_groups', $defaultGroups);
            }
        }
    }
}
