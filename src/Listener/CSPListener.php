<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Listener;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Event\CSPHeaderEvent;
use Aubes\CSPBundle\Model\CSPPolicy;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CSPListener
{
    /**
     * @param list<string> $reportRoutes
     */
    public function __construct(
        private readonly CSP $csp,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $reportRoutes,
    ) {
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->csp->isEnabled()) {
            return;
        }

        if ($event->getRequest()->attributes->get('_csp_disabled', false)) {
            return;
        }

        if (\in_array($event->getRequest()->attributes->get('_route', null), $this->reportRoutes, true)) {
            return;
        }

        $response = $event->getResponse();

        /** @var list<array<string, mixed>> $reportTo */
        $reportTo = [];
        /** @var list<string> $reportingEndpoints */
        $reportingEndpoints = [];

        /** @var array<string, list<CSPPolicy>> $policiesByHeader */
        $policiesByHeader = [];

        /** @var list<string> $currentGroupNames */
        $currentGroupNames = (array) $event->getRequest()->attributes->get('_csp_groups', []);

        $activePolicies = $this->csp->getPolicies($currentGroupNames);

        if ($activePolicies !== []) {
            $this->dispatcher->dispatch(new CSPHeaderEvent($event->getRequest(), $activePolicies));
        }

        foreach ($activePolicies as $policy) {
            $headerName = $policy->isReportOnly() ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $policiesByHeader[$headerName][] = $policy;

            $policyReportTo = $policy->getReportTo();
            if ($policyReportTo !== null) {
                $reportingEndpoints[] = $policyReportTo->renderReportingEndpoints();

                if ($policy->isBCSupport()) {
                    $reportTo[] = $policyReportTo->renderReportTo();
                }
            }
        }

        foreach ($policiesByHeader as $headerName => $policies) {
            if (\count($policies) > 1) {
                throw new \LogicException(\sprintf('Multiple groups resolve to the same header "%s". Each request supports at most one enforcing group and one report-only group.', $headerName));
            }

            $response->headers->set($headerName, $policies[0]->render());
        }

        if ($reportingEndpoints !== []) {
            $response->headers->set('Reporting-Endpoints', \implode(', ', $reportingEndpoints));
        }

        if ($reportTo !== []) {
            $response->headers->set('Report-To', \json_encode($reportTo, \JSON_THROW_ON_ERROR));
        }
    }
}
