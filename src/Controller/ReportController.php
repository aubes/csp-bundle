<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Controller;

use Aubes\CSPBundle\CSP;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ReportController extends AbstractController
{
    protected LoggerInterface $logger;
    protected string $level;

    public function __construct(LoggerInterface $logger, string $level = LogLevel::WARNING)
    {
        $this->logger = $logger;
        $this->level = $level;
    }

    public function __invoke(string $group, Request $request, CSP $csp): Response
    {
        if (!$csp->hasGroup($group)) {
            throw $this->createNotFoundException();
        }

        $this->logger->log($this->level, 'csp_report', ['extra' => [
            'group' => $group,
            'content' => $request->getContent(),
        ]]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
