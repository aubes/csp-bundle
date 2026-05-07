# Configuration

## Full reference

```yaml
# config/packages/csp.yaml
csp:
    # Required when multiple groups are defined.
    # When only one group exists, it becomes the default automatically.
    default_group: default

    # Apply the default group to every response without needing attributes.
    # Default: false (opt-in).
    auto_default: true

    # Force all groups into report-only mode (useful in dev).
    debug: false

    # Optional: log violations via Monolog.
    report_logger:
        logger_id: ~  # Service ID (default: logger)
        level: ~      # Log level (default: WARNING)

    groups:
        default:
            # Base preset: strict, permissive, or api.
            preset: strict

            # Send the header as Report-Only instead of enforcing.
            report_only: false

            # Your custom directives (merged with preset).
            policies:
                connect_src:
                    - self
                    - 'https://api.example.com'
                img_src:
                    - self

            # Violation reporting.
            reporting:
                max_age: 3600
                group_name: ~           # Override the report-to group name
                backward_compatibility: false  # Emit legacy Report-To header
                endpoints:
                    - csp_report        # Symfony route name
```

## Presets

Presets provide sensible defaults. Your custom `policies` are merged on top (they extend, never replace).

### `strict`

Nonce-based policy with `strict-dynamic`. Recommended as a starting point when your templates can inject nonces on inline scripts and styles.

```text
default-src 'self'
script-src  'strict-dynamic' 'unsafe-inline' https:
style-src   'self'
object-src  'none'
base-uri    'none'
form-action 'self'
frame-ancestors 'self'
upgrade-insecure-requests
```

> **Important:** `strict-dynamic` requires nonces. Without the [Twig nonce helpers](twig.md), all inline scripts are blocked.

> **Browser support:** `strict-dynamic` is part of CSP Level 3 and supported by modern Chromium, Firefox, and Safari. Older browsers ignore it and fall back to the rest of the source list, so pair it with `'self'` or a host allowlist if you need graceful degradation. See [caniuse.com/mdn-http_headers_content-security-policy_strict-dynamic](https://caniuse.com/mdn-http_headers_content-security-policy_strict-dynamic) for current support.

> **About `'unsafe-inline'` and `https:`:** these are deliberate fallbacks for CSP Level 1/2 browsers that don't understand `'strict-dynamic'`. CSP Level 3 browsers ignore both when `'strict-dynamic'` is present, so the policy stays strict where it counts.

### `permissive`

Allows `unsafe-inline` for scripts and styles. Suitable for legacy apps that cannot adopt nonces yet.

```text
default-src 'self'
script-src  'self' 'unsafe-inline' 'unsafe-eval'
style-src   'self' 'unsafe-inline'
img-src     'self' data:
font-src    'self'
connect-src 'self' https:
object-src  'none'
base-uri    'self'
form-action 'self'
frame-ancestors 'self'
upgrade-insecure-requests
```

### `api`

Locks everything down. Designed for JSON APIs with no HTML rendering.

```text
default-src     'none'
frame-ancestors 'none'
base-uri        'none'
form-action     'none'
```

## Directive names

In YAML configuration, use **underscores** instead of hyphens:

```yaml
policies:
    script_src: [self]
    style_src_elem: [self]
    frame_ancestors: [self]
    upgrade_insecure_requests: []
```

All [CSP Level 3 directives](https://www.w3.org/TR/CSP3/) are supported:

| Directive | YAML key |
|---|---|
| `default-src` | `default_src` |
| `script-src` | `script_src` |
| `script-src-elem` | `script_src_elem` |
| `script-src-attr` | `script_src_attr` |
| `style-src` | `style_src` |
| `style-src-elem` | `style_src_elem` |
| `style-src-attr` | `style_src_attr` |
| `img-src` | `img_src` |
| `font-src` | `font_src` |
| `connect-src` | `connect_src` |
| `media-src` | `media_src` |
| `object-src` | `object_src` |
| `frame-src` | `frame_src` |
| `child-src` | `child_src` |
| `worker-src` | `worker_src` |
| `manifest-src` | `manifest_src` |
| `base-uri` | `base_uri` |
| `form-action` | `form_action` |
| `frame-ancestors` | `frame_ancestors` |
| `upgrade-insecure-requests` | `upgrade_insecure_requests` |
| `require-trusted-types-for` | `require_trusted_types_for` |
| `trusted-types` | `trusted_types` |
| `webrtc` | `webrtc` |

> **Browser support:** Trusted Types (`require-trusted-types-for`, `trusted-types`) and `webrtc` have partial or experimental support. Non-supporting browsers ignore them silently (graceful degradation), so you cannot rely on them for cross-browser protection. Check [caniuse.com](https://caniuse.com/) for each directive before relying on it in production.

## Debug mode

Force all groups into report-only mode during development:

```yaml
# config/packages/csp.yaml
when@dev:
    csp:
        debug: true
```

Violations are reported in the browser console without blocking anything. Combine with `report_logger` to see them in your Symfony logs.

## Multi-group constraint

Each request supports at most **one enforcing group** and **one report-only group**. Applying two groups of the same mode throws a `LogicException`.

This is by design: the HTTP spec says multiple `Content-Security-Policy` headers are intersected (most restrictive wins), which would silently break additive policies.

The typical multi-group use case is [gradual rollout](reporting.md#gradual-rollout): enforce a permissive policy while evaluating a strict one in report-only mode.
