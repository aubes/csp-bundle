<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Enum;

enum CSPDirective: string
{
    case DefaultSrc = 'default-src';
    case BaseUri = 'base-uri';
    case ScriptSrc = 'script-src';
    case ScriptSrcAttr = 'script-src-attr';
    case ScriptSrcElem = 'script-src-elem';
    case ChildSrc = 'child-src';
    case ConnectSrc = 'connect-src';
    case FontSrc = 'font-src';
    case FormAction = 'form-action';
    case FrameAncestors = 'frame-ancestors';
    case FrameSrc = 'frame-src';
    case ImgSrc = 'img-src';
    case ManifestSrc = 'manifest-src';
    case MediaSrc = 'media-src';
    case ObjectSrc = 'object-src';
    case RequireTrustedTypesFor = 'require-trusted-types-for';
    case Sandbox = 'sandbox';
    case StyleSrc = 'style-src';
    case StyleSrcAttr = 'style-src-attr';
    case StyleSrcElem = 'style-src-elem';
    case TrustedTypes = 'trusted-types';
    case UpgradeInsecureRequests = 'upgrade-insecure-requests';
    case Webrtc = 'webrtc';
    case WorkerSrc = 'worker-src';
}
