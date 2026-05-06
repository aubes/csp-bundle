# Content Security Policy Bundle

[![CI](https://github.com/aubes/csp-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/aubes/csp-bundle/actions/workflows/php.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/aubes/csp-bundle)](https://packagist.org/packages/aubes/csp-bundle)
[![PHP Version](https://img.shields.io/packagist/dependency-v/aubes/csp-bundle/php)](https://packagist.org/packages/aubes/csp-bundle)
[![License](https://img.shields.io/packagist/l/aubes/csp-bundle)](https://packagist.org/packages/aubes/csp-bundle)

A Symfony bundle that makes [Content-Security-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP) headers simple to manage.

CSP headers protect your users from XSS, clickjacking, and data injection attacks.

## What it does

- [**Presets**](docs/configuration.md#presets) to get started in seconds: `strict`, `permissive`, or `api`
- [**Nonces and hashes**](docs/twig.md) via Twig helpers, no manual header manipulation
- [**Per-controller policies**](docs/getting-started.md#per-controller-policies) with PHP attributes (`#[CSPGroup]`, `#[CSPDisabled]`)
- [**Audit command**](docs/reporting.md#audit-command) to catch misconfigurations before they reach production
- [**Violation reporting**](docs/reporting.md) with modern `Reporting-Endpoints` and legacy `Report-To`
- [**Gradual rollout**](docs/reporting.md#gradual-rollout): enforce a permissive policy today, evaluate a strict one in report-only mode, switch when ready
- [**Dynamic directives**](docs/advanced.md#dynamic-directives) at runtime from controllers
- [**Worker-ready**](docs/advanced.md#worker-mode-frankenphp--roadrunner): FrankenPHP, RoadRunner

## Requirements

- PHP >= 8.2
- Symfony ^6.4 \| ^7.4 \| ^8.0

## Quick start

```shell
composer require aubes/csp-bundle
```

```yaml
# config/packages/csp.yaml
csp:
    auto_default: true
    groups:
        default:
            preset: strict
```

That's it. Every response now has a strict Content-Security-Policy header with nonce support.

Add nonces to your inline scripts:

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}
```

For Twig nonce/hash support, install `symfony/twig-bundle`:

```shell
composer require symfony/twig-bundle
```

## Why CSP matters

Without CSP, any injected script runs with full page privileges. A single XSS vulnerability can steal cookies, redirect users, or exfiltrate data.

CSP tells the browser exactly which sources are allowed. Everything else is blocked. It's the last line of defense when your input validation or output encoding has a gap.

## Documentation

- [Getting started](docs/getting-started.md): installation, first policy, nonces, per-controller attributes
- [Configuration](docs/configuration.md): presets, directives, debug mode, worker support
- [Twig helpers](docs/twig.md): block tags, nonce functions, hashes
- [Reporting](docs/reporting.md): violation endpoints, audit command, gradual rollout
- [Troubleshooting](docs/troubleshooting.md): common errors and fixes
- [Advanced](docs/advanced.md): dynamic directives, route defaults, worker mode, profiler
- [Upgrading from 1.x to 2.0](UPGRADE-2.0.md): breaking changes and migration steps

## License

[MIT](LICENSE)
