# Content Security Policy Bundle

[![CI](https://github.com/aubes/csp-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/aubes/csp-bundle/actions/workflows/php.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/aubes/csp-bundle)](https://packagist.org/packages/aubes/csp-bundle)
[![PHP Version](https://img.shields.io/packagist/dependency-v/aubes/csp-bundle/php)](https://packagist.org/packages/aubes/csp-bundle)
[![License](https://img.shields.io/packagist/l/aubes/csp-bundle)](https://packagist.org/packages/aubes/csp-bundle)

Symfony bundle for managing [Content-Security-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP) headers.

- **Presets** to get started in seconds (`strict`, `permissive`, `api`)
- **Nonces and hashes** via Twig helpers and block tags
- **Multi-group policies** with per-controller PHP attributes
- **Audit command** (`csp:check`) to detect misconfigurations
- **Violation reporting** with Reporting-Endpoints and legacy Report-To support
- **Worker-ready** (FrankenPHP / RoadRunner)

**Requirements:** PHP >= 8.2, Symfony ^6.4 | ^7.4 | ^8.0

## Installation

```shell
composer require aubes/csp-bundle
```

For Twig nonce/hash support:

```shell
composer require symfony/twig-bundle
```

## Configuration

```yaml
# config/packages/csp.yaml
csp:
    # Required when multiple groups are defined
    # When only one group is defined, it becomes the default
    default_group: default

    # Automatically add default group CSP headers to every response
    auto_default: true

    # Force report-only mode on all groups (useful in dev)
    debug: false

    groups:
        default:
            # Use a preset as a base (strict, permissive, api)
            preset: strict

            # Report-Only instead of enforcing
            report_only: false

            # Additional policies (merged with preset)
            policies:
                connect_src:
                    - self
                    - 'https://api.example.com'

        admin:
            preset: permissive
            policies:
                img_src:
                    - self
                    - 'https://cdn.example.com'

        api:
            preset: api
```

### Presets

Three built-in presets provide sensible defaults:

| Preset | Description |
|---|---|
| `strict` | Nonce-based with `strict-dynamic`, `object-src 'none'`, `base-uri 'none'` |
| `permissive` | `'self'` + `'unsafe-inline'`, suitable for legacy apps that cannot use nonces |
| `api` | `default-src 'none'`, no framing, no forms |

Preset policies are merged with your custom policies. Your policies extend the preset, they don't replace it.

> **Note:** The `strict` preset uses `strict-dynamic` which requires nonces to work. Without nonces, all scripts will be blocked. Make sure you use the Twig nonce helpers in your templates:
>
> ```twig
> {# Block tag (recommended) #}
> {% csp_script %}
>     document.getElementById('app').textContent = 'Hello!';
> {% end_csp_script %}
>
> {# Or manual nonce attribute #}
> <script {{ csp_script_nonce() }}>
>     // ...
> </script>
> ```

### Directive names

Use underscore-separated names in YAML configuration:

```yaml
policies:
    script_src: [self]
    style_src_elem: [self]
    frame_ancestors: [self]
    upgrade_insecure_requests: []
```

## Usage

### Applying CSP headers

#### Auto default

With `auto_default: true`, the default group is applied to every response automatically.

#### PHP attributes

Use attributes on controllers to select groups or disable CSP:

```php
use Aubes\CSPBundle\Attribute\CSPGroup;
use Aubes\CSPBundle\Attribute\CSPDisabled;

// Apply a specific group
#[CSPGroup('admin')]
class AdminController extends AbstractController
{
    // All actions use the "admin" CSP group
}

// Apply one enforcing + one report-only group
#[CSPGroup('default')]
#[CSPGroup('strict_ro')]
class DashboardController extends AbstractController {}

// Override at method level
class PageController extends AbstractController
{
    #[CSPGroup('strict')]
    public function secure(): Response {}
}

// Disable CSP entirely
#[CSPDisabled]
class WebhookController extends AbstractController {}
```

> **Multi-group constraint:** each request supports at most one enforcing group and one report-only group. Applying two groups of the same mode (e.g. two enforcing groups) will throw a `LogicException`. This is by design: the HTTP spec says multiple `Content-Security-Policy` headers are intersected (most restrictive wins), which would silently break additive policies.

#### Route defaults

You can also configure groups via route defaults:

```yaml
# config/routes.yaml
admin_route:
    path: /admin
    defaults:
        _csp_groups: [admin]

webhook_route:
    path: /webhook
    defaults:
        _csp_disabled: true
```

#### Dynamic directives

Add directives at runtime from a controller:

```php
use Aubes\CSPBundle\CSP;

class ExampleController extends AbstractController
{
    public function __invoke(CSP $csp): Response
    {
        // Add to default group
        $csp->addDirective('script-src', 'https://cdn.example.com');

        // Add to a specific group
        $csp->addDirective('img-src', 'https://images.example.com', 'admin');

        return $this->render('example.html.twig');
    }
}
```

### Twig: nonces

Nonces are generated per-request and automatically added to the CSP header.

#### Block tags (recommended)

Wraps your inline script/style with a nonce automatically:

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}

{% csp_style %}
    body { font-family: sans-serif; }
{% end_csp_style %}

{# With a specific group #}
{% csp_script 'admin' %}
    console.log('admin');
{% end_csp_script %}
```

#### Functions

For manual nonce attributes:

```twig
{# script-src nonce #}
<script {{ csp_script_nonce() }}>
    // ...
</script>

{# style-src nonce #}
<style {{ csp_style_nonce() }}>
    body { font-family: sans-serif; }
</style>

{# Generic nonce with directive #}
<script {{ csp_nonce('script-src') }}>
    // ...
</script>

{# With a specific group #}
<script {{ csp_script_nonce('admin') }}>
    // ...
</script>
```

### Twig: hashes

Register a hash for inline content without using a nonce:

```twig
{% do csp_hash('script-src', "alert('hello')") %}

{# With a specific algorithm (default: sha256) #}
{% do csp_hash('script-src', content, 'sha384') %}

{# With a specific group #}
{% do csp_hash('script-src', content, 'sha256', 'admin') %}
```

## Reporting

### Configuration

Enable [Reporting-Endpoints](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Reporting-Endpoints) (modern) and optionally [Report-To](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Report-To) (legacy):

```yaml
# config/packages/csp.yaml
csp:
    groups:
        default:
            reporting:
                max_age: 3600
                # Optional: override the group name in the report-to directive
                group_name: ~
                # Emit legacy Report-To header alongside Reporting-Endpoints
                backward_compatibility: false
                endpoints:
                    - csp_report  # Symfony route name
```

> **Note:** Multiple endpoints can be configured, but only the first one is used with the modern `Reporting-Endpoints` header (which supports a single URL per group). Additional endpoints are only used when `backward_compatibility: true` is enabled, as the legacy `Report-To` header supports failover across multiple URLs.

### Built-in report controller

A controller is included to receive CSP violation reports (path: `/csp-report/{group}`, route: `csp_report`). Each report is dispatched as a `CSPViolationEvent`.

Import the bundle's routing:

```yaml
# config/routes.yaml
csp_report:
    resource: '@CSPBundle/Resources/config/routing.yaml'
```

Or define the route manually:

```yaml
# config/routes.yaml
csp_report:
    path: /csp-report/{group}
    controller: Aubes\CSPBundle\Controller\ReportController::__invoke
    methods: ['POST']
```

### Handling violation reports

Listen to `CSPViolationEvent` to handle reports however you want (log, Sentry, database, Slack, etc.):

```php
use Aubes\CSPBundle\Event\CSPViolationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class CSPViolationListener
{
    public function __invoke(CSPViolationEvent $event): void
    {
        // $event->group  : the CSP group name
        // $event->report : the sanitized report data
    }
}
```

### Built-in logger

Optionally enable the built-in log listener to log violations via Monolog:

```yaml
# config/packages/csp.yaml
csp:
    report_logger:
        logger_id: ~  # Logger service ID (default: logger)
        level: ~      # Log level (default: WARNING)
```

Without `report_logger`, events are dispatched but not logged.

## Audit command

Audit your CSP configuration against common security pitfalls:

```shell
php bin/console csp:check
```

The command checks for: missing critical directives, `unsafe-inline`/`unsafe-eval` usage, wildcard sources, HTTP/IP sources, `strict-dynamic` without nonce, trusted types consistency, and missing reporting.

```
 ------- ------- ----------- -----------------------------------------------------------
  Level   Group   Directive   Finding
 ------- ------- ----------- -----------------------------------------------------------
  ERROR   weak    script-src  'unsafe-inline' allows execution of arbitrary inline scripts.
  WARN    weak    object-src  object-src should be 'none' unless plugins are explicitly needed.
  INFO    default report-to   No reporting endpoint configured.
 ------- ------- ----------- -----------------------------------------------------------
```

## Web Debug Toolbar

When `symfony/web-profiler-bundle` is installed, a CSP panel is available in the Symfony profiler showing:
- Active group(s) and enabled/disabled status
- Full CSP header value
- Parsed directives with color-coded sources (keywords, nonces, hashes, URLs)
- Report-Only header if present
- Reporting endpoints

## Debug mode

Set `debug: true` to force all groups into `report-only` mode. Useful during development to detect violations without breaking the page:

```yaml
# config/packages/csp.yaml
when@dev:
    csp:
        debug: true
```

## Gradual rollout

Hardening your CSP without breaking your site is easy with multi-group policies. The idea: enforce a permissive policy today, evaluate a strict one in parallel, then switch when ready.

### Step 1: enforce permissive, evaluate strict

```yaml
csp:
    default_group: default
    groups:
        default:
            preset: permissive

        strict:
            preset: strict
            report_only: true
            reporting:
                max_age: 3600
                endpoints:
                    - csp_report
```

Apply both groups to your controllers:

```php
#[CSPGroup('default')]
#[CSPGroup('strict')]
class MyController extends AbstractController {}
```

The `permissive` group enforces (nothing breaks), while `strict` runs in report-only mode. Violations are sent to your reporting endpoint.

### Step 2: fix violations

Review reports and add nonces to your inline scripts and styles:

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}
```

### Step 3: switch to strict

Once reports are clean, promote `strict` to enforcing and remove the permissive group:

```yaml
csp:
    default_group: default
    groups:
        default:
            preset: strict
            reporting:
                max_age: 3600
                endpoints:
                    - csp_report
```

## Worker mode (FrankenPHP / RoadRunner)

The `CSP` service implements `ResetInterface`. Nonces and dynamic directives are automatically reset between requests in long-running processes.
