# Reporting

CSP violation reports tell you when the browser blocks (or would block) a resource. This is essential for detecting misconfigurations and monitoring real-world attacks.

## Setup

### 1. Configure reporting on your group

```yaml
# config/packages/csp.yaml
csp:
    groups:
        default:
            preset: strict
            reporting:
                max_age: 3600
                endpoints:
                    - csp_report  # Symfony route name
```

This adds a `report-to` directive to your CSP header and a `Reporting-Endpoints` header to the response.

> **Browser support:** `Reporting-Endpoints` is not yet universally supported. For broader coverage, enable the [legacy `Report-To`](#legacy-report-to-support) header in parallel. Check [caniuse.com/mdn-http_headers_reporting-endpoints](https://caniuse.com/mdn-http_headers_reporting-endpoints) for current status before relying on it.

### 2. Import the built-in route

```yaml
# config/routes.yaml
csp_report:
    resource: '@CSPBundle/Resources/config/routing.yaml'
```

This registers a `POST /csp-report/{group}` endpoint that receives violation reports from browsers.

### 3. Handle violations

Each report dispatches a `CSPViolationEvent`. Listen to it however you want:

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

Send violations to Sentry, a database, Slack, or wherever makes sense for your team.

### Built-in logger (optional)

Don't want to write a listener? Enable the built-in logger:

```yaml
csp:
    report_logger:
        logger_id: ~  # defaults to "logger"
        level: ~      # defaults to WARNING
```

Violations are logged via Monolog. Without this config, events are dispatched but not logged.

## Legacy Report-To support

The modern `Reporting-Endpoints` header supports one URL per endpoint. If you need failover across multiple URLs, enable the legacy `Report-To` header:

```yaml
reporting:
    backward_compatibility: true
    endpoints:
        - csp_report
        - csp_report_fallback
```

> **Note:** Multiple endpoints can be configured, but only the first one is used with the modern `Reporting-Endpoints` header. Additional endpoints are only used when `backward_compatibility: true` is enabled, as the legacy `Report-To` header supports failover across multiple URLs.

## Gradual rollout

Hardening your CSP without breaking your site. The idea: enforce a permissive policy today, evaluate a strict one in parallel, then switch when ready.

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

Apply both groups on your controllers:

```php
#[CSPGroup('default')]
#[CSPGroup('strict')]
class MyController extends AbstractController {}
```

The browser enforces the `permissive` policy (nothing breaks) and reports violations against the `strict` policy. Two separate headers, two different behaviors.

### Step 2: fix violations

Review reports and add nonces to inline scripts and styles:

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}
```

See [Twig helpers](twig.md) for the full list of available functions.

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

> **Important:** Keep reporting enabled after switching to enforce mode. Browser extensions, third-party scripts, and user-specific flows can trigger violations your test environment will not reproduce.

## Audit command

Catch common mistakes before deploying:

```shell
php bin/console csp:check
```

The command checks for: missing critical directives, `unsafe-inline`/`unsafe-eval` usage, wildcard sources, HTTP/IP sources, `strict-dynamic` without nonce, trusted types consistency, and missing reporting.

```text
 ------- ------- ----------- -----------------------------------------------------------
  Level   Group   Directive   Finding
 ------- ------- ----------- -----------------------------------------------------------
  ERROR   weak    script-src  'unsafe-inline' allows execution of arbitrary inline scripts.
  WARN    weak    object-src  object-src should be 'none' unless plugins are explicitly needed.
  INFO    default report-to   No reporting endpoint configured.
 ------- ------- ----------- -----------------------------------------------------------

Found 1 error(s), 1 warning(s), 1 info(s)
```

The command exits with code `1` if any finding is at `ERROR` level, making it CI-friendly.
