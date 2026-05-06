<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Enum;

enum CSPSource: string
{
    case Self = 'self';
    case UnsafeEval = 'unsafe-eval';
    case WasmUnsafeEval = 'wasm-unsafe-eval';
    case UnsafeHashes = 'unsafe-hashes';
    case UnsafeInline = 'unsafe-inline';
    case None = 'none';
    case StrictDynamic = 'strict-dynamic';
    case ReportSample = 'report-sample';
    case InlineSpeculationRules = 'inline-speculation-rules';
    case TrustedTypesEval = 'trusted-types-eval';
    case ReportSha256 = 'report-sha256';
    case ReportSha384 = 'report-sha384';
    case ReportSha512 = 'report-sha512';

    public function quoted(): string
    {
        return "'" . $this->value . "'";
    }
}
