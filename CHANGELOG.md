# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1]

### Fixed

- **Worker mode**: the `CSP` service is now tagged `kernel.reset`, so its state is reset between requests in long-running runtimes (FrankenPHP worker). Previously `reset()` was never called: nonces, hashes, dynamic directives and `setEnabled(false)` leaked into subsequent requests, keeping previously issued nonces valid and growing the header unboundedly.
- **`upgrade-insecure-requests`**: the directive was silently dropped from the header because it has no sources. It is now rendered, both from the `strict` and `permissive` presets and from `upgrade_insecure_requests: []` in `policies`. The same applies to `sandbox: []`, which is valid without value and applies all restrictions. Other directives configured without sources are still omitted.
- **Configuration**: a missing `reporting.max_age` triggered a PHP warning and a `TypeError` when the `CSP` service was built. It is now required, and must be greater than or equal to 0.
- **Twig block tags**: `{% csp_script %}` and `{% csp_style %}` now close their output buffer when the block body throws an exception while the template is rendered with `display()` (for example `AbstractController::stream()`). `render()` was not affected.
- **Docs**: the gradual rollout recipe now targets the report-only group in step 2 (`{% csp_script 'strict' %}`). Without a group name, the nonce was also added to the enforced group, where it disabled `'unsafe-inline'` and blocked the inline scripts not migrated yet.
- `csp:check` reports `'strict-dynamic'` without nonce or hash as `INFO` instead of `ERROR`, since nonces are added at runtime by the Twig helpers. The command no longer exits with code `1` on the `strict` preset.
- `csp:check` no longer reports `'unsafe-inline'` as an error when `'strict-dynamic'` is present in the same directive (CSP Level 3 browsers ignore `'unsafe-inline'` in that case, so it's a CSP1/2 fallback, not a vulnerability).

## [2.0.0]

### Breaking changes

- **PHP >= 8.2 required** (was PHP 7.4+)
- **Symfony ^6.4 | ^7.4 | ^8.0 required** (was Symfony 5.4+)
- `CSPDirective` and `CSPSource` are now PHP backed enums in `Aubes\CSPBundle\Enum\` (was classes with constants in `Aubes\CSPBundle\`)
- `CSPPolicy` moved to `Aubes\CSPBundle\Model\CSPPolicy` (was `Aubes\CSPBundle\CSPPolicy`)
- Directive names in YAML config use underscores: `script_src` (was `script-src`)
- `ReportTo::render()` removed, use `renderReportTo()` or `renderReportingEndpoints()`
- Nonces now use base64 encoding (was hex). If you were reading nonce values directly, update your code to expect base64
- `CSP::addGroup()` now throws `InvalidArgumentException` if the group already exists
- `ReportController` no longer accepts `LoggerInterface`: it dispatches a `CSPViolationEvent` instead. If you injected or extended the controller, update accordingly

### Added

- **Presets**: built-in `strict`, `permissive`, and `api` presets for common CSP configurations
- **PHP attributes**: `#[CSPGroup('name')]` and `#[CSPDisabled]` on controllers/methods
- **Twig block tags**: `{% csp_script %}...{% end_csp_script %}` and `{% csp_style %}...{% end_csp_style %}` for automatic nonce wrapping
- **Hash support**: `csp_hash()` Twig function for sha256/384/512 hash-based CSP
- **Audit command**: `csp:check` inspects your configuration for 15 security pitfalls (missing directives, unsafe sources, wildcards, etc.)
- **Web Debug Toolbar**: CSP panel in the Symfony profiler (requires `symfony/web-profiler-bundle`)
- **Debug mode**: `debug: true` forces all groups into report-only mode
- **Reporting-Endpoints header**: modern reporting standard, alongside legacy `Report-To` via `backward_compatibility` option
- **Worker mode**: `CSP` service implements `ResetInterface` for FrankenPHP/RoadRunner
- **Violation events**: `CSPViolationEvent` dispatched on each report, handle violations your way (log, Sentry, database, etc.)
- **Optional built-in logger**: `report_logger` config registers a log listener for violations via Monolog
- **Multi-group nonce resolution**: nonces in Twig templates are automatically added to all active groups of the current request
- **Multi-group constraint**: each request supports at most one enforcing group and one report-only group. Applying two groups of the same mode throws a `LogicException`
- **CSP Level 3 directives**: `script-src-attr`, `script-src-elem`, `style-src-attr`, `style-src-elem`, `worker-src`, `manifest-src`, `webrtc`, `require-trusted-types-for`, `trusted-types`
- **CSP Level 3 sources**: `strict-dynamic`, `unsafe-hashes`, `wasm-unsafe-eval`, `report-sample`, `inline-speculation-rules`, `trusted-types-eval`
- `CSP::getGroups()` public method to access all registered policy groups
- Conditional service registration: Twig extension and data collector are only registered when their dependencies are available

### Fixed

- `image-src` directive renamed to correct `img-src`
- `ReportController` validates Content-Type, body size (10KB max), JSON format, and JSON depth (max 10 levels)
- `Reporting-Endpoints` header now correctly uses a single URL per endpoint name (per spec)

### Changed

- `symfony/twig-bundle` is now optional: install it explicitly if you use nonce/hash Twig helpers

[2.0.1]: https://github.com/aubes/csp-bundle/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/aubes/csp-bundle/compare/v1.0.0...v2.0.0
