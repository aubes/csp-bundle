# Advanced

## Dynamic directives

Add directives at runtime from a controller:

```php
use Aubes\CSPBundle\CSP;

class ExampleController extends AbstractController
{
    public function __invoke(CSP $csp): Response
    {
        // Add to the default group
        $csp->addDirective('script-src', 'https://cdn.example.com');

        // Add to a specific group
        $csp->addDirective('img-src', 'https://images.example.com', 'admin');

        return $this->render('example.html.twig');
    }
}
```

This is useful when a directive depends on runtime data (e.g. a CDN URL from a CMS, an iframe source from user settings).

## CSPHeaderEvent

For cross-cutting changes, listen to `CSPHeaderEvent`. It is dispatched on every response, after the active groups are resolved and before the headers are rendered. Listeners can mutate the active `CSPPolicy` instances directly.

```php
use Aubes\CSPBundle\Event\CSPHeaderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class TenantCSPListener
{
    #[AsEventListener]
    public function onCspHeader(CSPHeaderEvent $event): void
    {
        $tenant = $event->request->attributes->get('_tenant');
        if ($tenant === null) {
            return;
        }

        foreach ($event->policies as $policy) {
            $policy->addPolicy('connect-src', \sprintf('https://%s.api.example.com', $tenant));
        }
    }
}
```

The event exposes:

- `request` (`Symfony\Component\HttpFoundation\Request`): the current main request
- `policies` (`array<string, CSPPolicy>`): active policies for this request, keyed by group name

Mutations apply to the response being built. They do not persist across requests (the bundle resets policies between requests via `ResetInterface`).

> **Note:** the event is not dispatched when no policy is active (CSP disabled, sub-request, report route).

## Route defaults

As an alternative to PHP attributes, you can configure CSP groups directly in your route definitions:

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

This works the same as `#[CSPGroup('admin')]` and `#[CSPDisabled]` on controllers.

> **Note:** PHP attributes and route defaults are additive. When both are present, their groups are merged (duplicates removed). Use one or the other to avoid surprises.

## Web Debug Toolbar

When `symfony/web-profiler-bundle` is installed, a CSP panel appears in the Symfony profiler showing:

- Active group(s) and enabled/disabled status
- Full CSP header value
- Parsed directives with color-coded sources (keywords, nonces, hashes, URLs)
- Report-Only header if present
- Reporting endpoints

No configuration needed: the panel registers automatically when the profiler bundle is available.

## Worker mode (FrankenPHP)

The `CSP` service implements Symfony's `ResetInterface`. Nonces, dynamic directives, and group selections are automatically cleared between requests in long-running processes.

No configuration needed: the reset is handled by Symfony's `services_resetter`.
