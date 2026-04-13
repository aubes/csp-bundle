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

    public function quoted(): string
    {
        return "'" . $this->value . "'";
    }
}
