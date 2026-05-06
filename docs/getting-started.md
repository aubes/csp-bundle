# Getting started

## Installation

```shell
composer require aubes/csp-bundle
```

For Twig nonce/hash support:

```shell
composer require symfony/twig-bundle
```

## Your first policy

Create a minimal configuration:

```yaml
# config/packages/csp.yaml
csp:
    auto_default: true
    groups:
        default:
            preset: strict
```

The `strict` preset gives you:
- `script-src` with `strict-dynamic` (nonce-based)
- `object-src 'none'`
- `base-uri 'none'`
- `frame-ancestors 'self'`

Every response now includes a `Content-Security-Policy` header.

## Add nonces to your templates

The `strict` preset relies on `strict-dynamic` for scripts, so inline `<script>` tags need a nonce or hash to run. Inline `<style>` tags also need a nonce (the preset allows `style-src 'self'`, which covers external stylesheets but not inline blocks). External scripts and stylesheets loaded from `'self'` work without any extra setup.

### Block tags (recommended)

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}

{% csp_style %}
    body { font-family: sans-serif; }
{% end_csp_style %}
```

The bundle wraps your content in a `<script>` or `<style>` tag with a unique nonce, and adds it to the CSP header automatically.

### Manual nonce attributes

```twig
<script {{ csp_script_nonce() }}>
    // your code
</script>
```

> **Note:** See the [Twig helpers](twig.md) page for the full list of functions: hashes, group targeting, and more.

## Start in report-only mode

Not sure your policy won't break anything? Use report-only mode first:

```yaml
csp:
    auto_default: true
    groups:
        default:
            preset: strict
            report_only: true
            reporting:
                max_age: 3600
                endpoints:
                    - csp_report
```

Import the built-in report route:

```yaml
# config/routes.yaml
csp_report:
    resource: '@CSPBundle/Resources/config/routing.yaml'
```

The browser reports violations instead of blocking them. Check your logs to see what would break, fix it, then switch `report_only` to `false`.

## Per-controller policies

Use PHP attributes to apply different policies per controller:

```php
use Aubes\CSPBundle\Attribute\CSPGroup;
use Aubes\CSPBundle\Attribute\CSPDisabled;

#[CSPGroup('admin')]
class AdminController extends AbstractController {}

#[CSPDisabled]
class WebhookController extends AbstractController {}
```

> **Warning:** Each request supports at most one enforcing group and one report-only group. Applying two groups of the same mode throws a `LogicException`. See [multi-group constraint](configuration.md#multi-group-constraint) for details.

## Audit your configuration

Run the built-in audit command to catch common mistakes:

```shell
php bin/console csp:check
```

It checks for missing directives, unsafe sources, wildcards, and more.

## Next steps

- [Twig helpers](twig.md): nonces, hashes, block tags
- [Full configuration reference](configuration.md)
- [Reporting and violation handling](reporting.md)
- [Gradual rollout strategy](reporting.md#gradual-rollout)
