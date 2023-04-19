<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

class CSPDirective
{
    public const DEFAULT_SRC = 'default-src';
    public const BASE_URI = 'base-uri';
    public const SCRIPT_SRC = 'script-src';
    public const SCRIPT_SRC_ATTR = 'script-src-attr';
    public const SCRIPT_SRC_ELEM = 'script-src-elem';
    public const CHILD_SRC = 'child-src';
    public const CONNECT_SRC = 'connect-src';
    public const FONT_SRC = 'font-src';
    public const FORM_ACTION = 'form-action';
    public const FRAME_ANCESTORS = 'frame-ancestors';
    public const FRAME_SRC = 'frame-src';
    public const IMAGE_SRC = 'image-src';
    public const MANIFEST_SRC = 'manifest-src';
    public const MEDIA_SRC = 'media-src';
    public const OBJECT_SRC = 'object-src';
    public const SANDBOX = 'sandbox';
    public const STYLE_SRC = 'style-src';
    public const STYLE_SRC_ATTR = 'style-src-attr';
    public const STYLE_SRC_ELEM = 'style-src-elem';
    public const UPDATE_INSECURE_REQUESTS = 'upgrade-insecure-requests';
    public const WORKER_SRC = 'worker-src';

    public const ALL = [
        self::DEFAULT_SRC,
        self::BASE_URI,
        self::SCRIPT_SRC,
        self::SCRIPT_SRC_ATTR,
        self::SCRIPT_SRC_ELEM,
        self::CHILD_SRC,
        self::CONNECT_SRC,
        self::FONT_SRC,
        self::FORM_ACTION,
        self::FRAME_ANCESTORS,
        self::FRAME_SRC,
        self::IMAGE_SRC,
        self::MANIFEST_SRC,
        self::MEDIA_SRC,
        self::OBJECT_SRC,
        self::SANDBOX,
        self::STYLE_SRC,
        self::STYLE_SRC_ATTR,
        self::STYLE_SRC_ELEM,
        self::UPDATE_INSECURE_REQUESTS,
        self::WORKER_SRC,
    ];
}
