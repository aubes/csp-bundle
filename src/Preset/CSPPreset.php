<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Preset;

enum CSPPreset: string
{
    case Strict = 'strict';
    case Permissive = 'permissive';
    case Api = 'api';

    /**
     * @return array<string, list<string>>
     */
    public function policies(): array
    {
        return match ($this) {
            self::Strict => [
                'default-src' => ['self'],
                'script-src' => ['strict-dynamic'],
                'style-src' => ['self'],
                'object-src' => ['none'],
                'base-uri' => ['none'],
                'frame-ancestors' => ['self'],
                'upgrade-insecure-requests' => [],
            ],
            self::Permissive => [
                'default-src' => ['self'],
                'script-src' => ['self', 'unsafe-inline'],
                'style-src' => ['self', 'unsafe-inline'],
                'img-src' => ['self', 'data:'],
                'font-src' => ['self'],
                'object-src' => ['none'],
                'base-uri' => ['self'],
                'frame-ancestors' => ['self'],
                'upgrade-insecure-requests' => [],
            ],
            self::Api => [
                'default-src' => ['none'],
                'frame-ancestors' => ['none'],
                'base-uri' => ['none'],
                'form-action' => ['none'],
            ],
        };
    }
}
