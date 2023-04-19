<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

class CSPSource
{
    public const SELF = 'self';
    public const UNSAFE_EVAL = 'unsafe-eval';
    public const WASM_UNSAFE_EVAL = 'wasm-unsafe-eval';
    public const UNSAFE_HASHES = 'unsafe-hashes';
    public const UNSAFE_INLINE = 'unsafe-inline';
    public const NONE = 'none';
    public const STRICT_DYNAMIC = 'strict-dynamic';
    public const REPORT_SAMPLE = 'report-sample';

    public const ALL = [
        self::SELF,
        self::UNSAFE_EVAL,
        self::WASM_UNSAFE_EVAL,
        self::UNSAFE_HASHES,
        self::UNSAFE_INLINE,
        self::NONE,
        self::STRICT_DYNAMIC,
        self::REPORT_SAMPLE,
    ];
}
