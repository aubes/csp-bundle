# Content Security Policy Bundle

This Symfony bundle provides helper to configure [Content-Security-Policy](https://developer.mozilla.org/fr/docs/Web/HTTP/CSP) headers.

It is compatible with :
 * PHP 7.4
 * Symfony 5.4

## Installation

```shell
composer require aubes/csp-bundle
```

## Configuration

The configuration looks as follows :

```yaml
# config/packages/csp.yaml
csp:
    # Default name is required when multiple group are defined
    # When only one group is defined, it becomes the default group
    default_group: ~
    
    # Add default group CSP headers in each response
    auto_default: false

    groups:
        # Use 'Content-Security-Policy-Report-Only' header instead of 'Content-Security-Policy'
        report_only: false
        
        # Name of the policy group
        default_example:            
            policies:
                # Use directive name
                base-uri:
                    # Internal source are supported, and simple quote are automatically added
                    - self

                    # Constant can be used for internal source
                    - !php/const Aubes\CSPBundle\CSPSource::SELF
                    
                    # Source reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/Sources
                    - 'https://example.com'
                
                # Use Php constant instead of directive name
                !php/const Aubes\CSPBundle\CSPDirective::SCRIPT_SRC:
                    - # Source

        another_group:
            # [...]
```
## Usage

### Add CSP Headers

#### Auto default

If the `auto_default` configuration is enabled, the default group is injected in each response.

#### Manually

```yaml
# config/routes.yaml
Example_routes:
    # [...]
    defaults:
        _csp_group: # Group list
```

### Source nonce

Twig functions are available to add inline element `nonce` in your template.

#### csp_nonce

Arguments:
* directive: name of the csp directive # required
* groupName: Group name
* nonce: base 64 nonce id

```html
<!-- templates/example.html.twig -->

<!-- Add a generated nonce on an inline element in the default group -->
<script {{ csp_nonce('srcipt-src') }}>
    // [...]
</script>

<!-- Add a generated nonce on an inline element in a specific group -->
<script {{ csp_nonce('srcipt-src', 'default_example') }}>
// [...]
</script>

<!-- Add a base64 custom nonce on an inline element in a specific group -->
<script {{ csp_nonce('srcipt-src', 'default_example', 'MTIzNDU2') }}>
// [...]
</script>
```

#### csp_script_nonce

Arguments:
* groupName: Group name
* nonce: base 64 nonce id

#### csp_style_nonce

Arguments:
* groupName: Group name
* nonce: base 64 nonce id

### Report

#### Configuration

Enable [report-to](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/report-to) in the configuration :

```yaml
# config/packages/csp.yaml
csp:
    groups:
        default_example:
            reporting:
                group_name: ~ # Override the group name
                
                # Add report-uri backward compatibility
                backward_compatibility: false
                
                max_age: 3600
                endpoints:
                    - # Symfony route
```

#### Build-in controller

A build-in controller can log report (path: `/csp-report/{group}`, name: `csp_report`)

To use the build-in controller to log reports :

```yaml
# config/routes.yaml
csp:
    resource: '@CSPBundle/Resources/config/routing.yaml'
```

Add the route in a report :

```yaml
# config/packages/csp.yaml
csp:
    groups:
        default_example:
            reporting:
                # [...]
                endpoints:
                    - 'csp_route'
```

#### Build-in controller Logger

To configure the Logger of this controller :

```yaml
# config/packages/csp.yaml
csp:
    report_logger:
        logger_id: ~ # Logger Service Id
        level: ~ # Log level, default is WARNING
```
