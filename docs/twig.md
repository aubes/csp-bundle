# Twig helpers

The bundle provides Twig functions and block tags to add nonces and hashes to your CSP policies. They require `symfony/twig-bundle`:

```shell
composer require symfony/twig-bundle
```

## Block tags (recommended)

Block tags wrap your inline code in a `<script>` or `<style>` tag with a unique nonce, and register it in the CSP header automatically.

```twig
{% csp_script %}
    document.getElementById('app').textContent = 'Hello!';
{% end_csp_script %}

{% csp_style %}
    body { font-family: sans-serif; }
{% end_csp_style %}
```

Rendered output:

```html
<script nonce="r4nd0m">
    document.getElementById('app').textContent = 'Hello!';
</script>
```

### Targeting a specific group

```twig
{% csp_script 'admin' %}
    console.log('admin panel');
{% end_csp_script %}

{% csp_style 'admin' %}
    .sidebar { width: 250px; }
{% end_csp_style %}
```

## Nonce functions

For cases where you need the nonce attribute directly (external scripts, custom tags):

```twig
{# script-src nonce #}
<script {{ csp_script_nonce() }}>
    // ...
</script>

{# style-src nonce #}
<style {{ csp_style_nonce() }}>
    body { font-family: sans-serif; }
</style>

{# Generic nonce with any directive #}
<script {{ csp_nonce('script-src') }}>
    // ...
</script>
```

### Targeting a specific group

Pass the group name as the first (or second) argument:

```twig
<script {{ csp_script_nonce('admin') }}>
    // ...
</script>

<style {{ csp_style_nonce('admin') }}>
    // ...
</style>

<script {{ csp_nonce('script-src', 'admin') }}>
    // ...
</script>
```

> **Note:** Each call generates a fresh nonce per request. The nonce is automatically added to the corresponding directive in the CSP header.

## Hash block tags

When the inline content is static, prefer hashes over nonces: the page can be cached publicly because the hash never changes (a nonce changes per request and breaks shared caches).

```twig
{% csp_script_hash %}
    alert('hello')
{% end_csp_script_hash %}

{% csp_style_hash %}
    body { font-family: sans-serif; }
{% end_csp_style_hash %}
```

Rendered output (no `nonce` attribute):

```html
<script>
    alert('hello')
</script>
```

The bundle captures the content, computes its sha256 hash, and adds `'sha256-...'` to the relevant directive automatically.

### Targeting a specific group

```twig
{% csp_script_hash 'admin' %}
    console.log('admin panel');
{% end_csp_script_hash %}
```

### `csp_hash` function

For external resources or cases where the content is not directly inside the template:

```twig
{% do csp_hash('script-src', "alert('hello')") %}
<script>alert('hello')</script>
```

Options:

```twig
{# Custom algorithm (default: sha256) #}
{% do csp_hash('script-src', content, 'sha384') %}

{# Targeting a specific group #}
{% do csp_hash('script-src', content, 'sha256', 'admin') %}
```

> **Warning:** The hash must match the content exactly, including whitespace. If you edit the inline code without updating the hash, the browser blocks it until the next page load regenerates the header. The block tags handle this automatically; the `csp_hash` function does not.

## Block tags vs nonce functions vs hashes

| Approach | Best for | Pros |
|---|---|---|
| Nonce block tags (`csp_script`, `csp_style`) | Inline scripts/styles that may change per request | Simplest, nonce handled automatically |
| Hash block tags (`csp_script_hash`, `csp_style_hash`) | Inline scripts/styles that are stable across requests | Cache-friendly (no per-request nonce), no `nonce` attribute in HTML |
| Nonce functions | External `<script>` tags, custom elements | Full control over the HTML tag |
| `csp_hash` function | Content not directly inside the template | Flexible, but you maintain the content/hash sync |

> **Note:** The `strict` preset uses `strict-dynamic`, which requires nonces to work. Without nonces or hashes, all inline scripts are blocked.
