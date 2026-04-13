# Upgrading from 1.x to 2.0

## Requirements

- PHP 8.2+ (was PHP 7.4+)
- Symfony 6.4, 7.4, or 8.0 (was Symfony 5.4+)

## Step-by-step migration

### 1. Update your YAML configuration

Directive names now use **underscores** instead of hyphens:

```diff
 csp:
     groups:
         default:
             policies:
-                script-src:
+                script_src:
                     - self
-                style-src:
+                style_src:
                     - self
-                img-src:
+                img_src:
                     - self
```

> **Note:** the `image-src` directive (which was incorrect) has been renamed to the correct `img_src`.

### 2. Update enum/class imports

`CSPDirective` and `CSPSource` are now backed enums:

```diff
- use Aubes\CSPBundle\CSPDirective;
- use Aubes\CSPBundle\CSPSource;
+ use Aubes\CSPBundle\Enum\CSPDirective;
+ use Aubes\CSPBundle\Enum\CSPSource;
```

`CSPPolicy` moved to the `Model` namespace:

```diff
- use Aubes\CSPBundle\CSPPolicy;
+ use Aubes\CSPBundle\Model\CSPPolicy;
```

### 3. Install `symfony/twig-bundle` if needed

`symfony/twig-bundle` is no longer a required dependency. If you use the Twig nonce/hash helpers, install it explicitly:

```shell
composer require symfony/twig-bundle
```

### 4. Update violation report handling

`ReportController` no longer logs violations directly. It now dispatches a `CSPViolationEvent` that you can listen to.

**If you relied on the built-in logging**, enable the optional logger:

```yaml
# config/packages/csp.yaml
csp:
    report_logger:
        logger_id: ~  # defaults to "logger"
        level: ~      # defaults to WARNING
```

**If you want custom handling**, register an event listener:

```php
use Aubes\CSPBundle\Event\CSPViolationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class CSPViolationListener
{
    public function __invoke(CSPViolationEvent $event): void
    {
        // $event->group, $event->report
    }
}
```

## Advanced (only if you use internal APIs)

The following changes only affect you if you interact with bundle classes directly in your code.

### `ReportTo::render()` removed

```diff
- $reportTo->render();
+ $reportTo->renderReportTo();          // Legacy Report-To header
+ $reportTo->renderReportingEndpoints(); // Modern Reporting-Endpoints header
```

### `CSP::addGroup()` rejects duplicates

`addGroup()` now throws an `InvalidArgumentException` if the group already exists:

```php
if (!$csp->hasGroup('my_group')) {
    $csp->addGroup($policy, 'my_group');
}
```

### Multi-group: one per mode

Applying two groups of the same mode (e.g. two enforcing groups) now throws a `LogicException`. Each request supports at most one enforcing group and one report-only group. If you were relying on multi-group merge, consolidate your directives into a single group.

### Nonce encoding changed to base64

Nonces are now base64-encoded (was hex). If you were reading nonce values directly, update your code to expect base64 values.
