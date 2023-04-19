<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Listener;

use Aubes\CSPBundle\CSP;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CSPListener
{
    protected CSP $csp;
    protected array $reportRoutes;

    public function __construct(CSP $csp, array $reportRoutes)
    {
        $this->csp = $csp;
        $this->reportRoutes = $reportRoutes;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->csp->isEnabled()) {
            return;
        }

        if (\in_array($event->getRequest()->attributes->get('_route', null), $this->reportRoutes)) {
            return;
        }

        $response = $event->getResponse();

        $reportTo = [];
        $currentGroupNames = (array) $event->getRequest()->attributes->get('_csp_groups', []);

        foreach ($this->csp->getPolicies($currentGroupNames) as $policy) {
            $headerName = $policy->isReportOnly() ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';

            $policyReportTo = $policy->getReportTo();
            if ($policyReportTo !== null) {
                $reportTo[] = $policyReportTo->render();
            }

            $response->headers->add([$headerName => $policy->render()]);
        }

        if (!empty($reportTo)) {
            $response->headers->add(['Report-To' => \json_encode($reportTo, \JSON_THROW_ON_ERROR)]);
        }
    }
}
