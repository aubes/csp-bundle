<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Controller;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Event\CSPViolationEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ReportController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    private const MAX_BODY_SIZE = 10240;
    private const MAX_JSON_DEPTH = 10;
    private const ALLOWED_CONTENT_TYPES = [
        'application/csp-report',
        'application/reports+json',
        'application/json',
    ];

    private const ALLOWED_REPORT_FIELDS = [
        'blocked-uri',
        'disposition',
        'document-uri',
        'effective-directive',
        'original-policy',
        'referrer',
        'script-sample',
        'status-code',
        'violated-directive',
        'source-file',
        'line-number',
        'column-number',
    ];

    private const ALLOWED_REPORTING_API_FIELDS = [
        'type',
        'age',
        'url',
        'user_agent',
    ];

    private const ALLOWED_REPORTING_API_BODY_FIELDS = [
        'blockedURL',
        'disposition',
        'documentURL',
        'effectiveDirective',
        'originalPolicy',
        'referrer',
        'sample',
        'statusCode',
        'violatedDirective',
        'sourceFile',
        'lineNumber',
        'columnNumber',
    ];

    public function __invoke(string $group, Request $request, CSP $csp): Response
    {
        if (!$csp->hasGroup($group)) {
            throw new NotFoundHttpException();
        }

        $contentType = $request->headers->get('Content-Type', '');
        if (!$this->isAllowedContentType($contentType)) {
            return new Response(null, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BODY_SIZE) {
            return new Response(null, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $decoded = \json_decode($body, true, self::MAX_JSON_DEPTH);
        if (!\is_array($decoded)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $this->dispatcher->dispatch(new CSPViolationEvent($group, $this->sanitizeReport($decoded)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $decoded
     *
     * @return array<mixed>
     */
    private function sanitizeReport(array $decoded): array
    {
        // Format: [{"type": "csp-violation", "body": {...}}] (Reporting API)
        if (\array_is_list($decoded)) {
            return \array_map(static function (mixed $entry): array {
                if (!\is_array($entry)) {
                    return [];
                }

                $sanitized = \array_intersect_key($entry, \array_flip(self::ALLOWED_REPORTING_API_FIELDS));

                if (isset($entry['body']) && \is_array($entry['body'])) {
                    $sanitized['body'] = \array_intersect_key(
                        $entry['body'],
                        \array_flip(self::ALLOWED_REPORTING_API_BODY_FIELDS),
                    );
                }

                return $sanitized;
            }, $decoded);
        }

        // Format: {"csp-report": {...}}
        if (isset($decoded['csp-report']) && \is_array($decoded['csp-report'])) {
            return ['csp-report' => \array_intersect_key(
                $decoded['csp-report'],
                \array_flip(self::ALLOWED_REPORT_FIELDS),
            )];
        }

        return [];
    }

    private function isAllowedContentType(string $contentType): bool
    {
        foreach (self::ALLOWED_CONTENT_TYPES as $allowed) {
            if (\str_starts_with($contentType, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
