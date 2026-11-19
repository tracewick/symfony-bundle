# Tracewick Symfony Bundle

[![CI](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/tracewick/symfony-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/tracewick/symfony-bundle)

> [!WARNING]
> Tracewick Symfony Bundle **0.x is alpha**. The public API is not stable until 1.0.0 — expect
> breaking changes in minor releases. Pin a version and read the changelog before upgrading.

Official Symfony integration for
[`tracewick/tracewick`](https://github.com/tracewick/tracewick) — a PHP sampling profiler on
[ext-excimer](https://www.mediawiki.org/wiki/Excimer) that writes profiles to a local spool
(recommended) or optionally to Grafana Pyroscope.

## Requirements

| | |
|---|---|
| PHP | 8.2–8.5 |
| Symfony | 6.4 / 7.x / 8.x (CI matrix: **6.4**, **7.4**, **8.1**) |
| Tracewick | `^0.2` |
| ext-excimer | suggested (fail-open no-op without it) |

Symfony 6.4 on PHP 8.1 is not supported (same PHP floor as the core library).

## Install

Install the package:

```bash
composer require tracewick/symfony-bundle
```

For releases with signed assets, [verify the release ZIP and SBOM](docs/verify-release.md).
Currently published releases are unsigned; Composer does not verify Cosign automatically.

The [contrib Flex recipe](https://github.com/symfony/recipes-contrib/pull/2116)
is pending review. Until it is merged, register the bundle (if not already registered)
and copy the config by hand:

```php
// config/bundles.php
return [
    // ...
    Tracewick\Bridge\Symfony\TracewickBundle::class => ['all' => true],
];
```

```bash
cp vendor/tracewick/symfony-bundle/config/packages/tracewick.yaml config/packages/tracewick.yaml
```

Install the extension separately when you want samples (prefer [PIE](https://php.github.io/pie/)):

```bash
pie install wikimedia/excimer
php -m | grep -i excimer
```

Minimal config:

```yaml
# config/packages/tracewick.yaml
tracewick:
    application_name: '%env(TRACEWICK_APPLICATION)%'
    spool_directory: '%env(TRACEWICK_SPOOL_DIR)%'
```

Set both variables in the application environment, or in `.env` when the application's
Symfony Dotenv bootstrap loads it. The full example above provides fallback values.

`enabled` defaults to `true`, `sample_rate` to `0.1`, `sink` to `spool`. Optional
keys (`label_controller`, `profile_console`, `profile_messenger`, `base_labels`,
Pyroscope) are documented in the fuller example:
[`config/packages/tracewick.yaml`](config/packages/tracewick.yaml).

## What it does

On each **main** HTTP request the bundle:

1. Starts a unit-of-work profile early (`kernel.request`, priority `4096`) with
   `route=unmatched` and the HTTP method.
2. After the router (priority `31`), sets the `route` label to the Symfony route **name**
   (never the raw URI). Optionally also sets `controller` when `label_controller: true`.
3. On `kernel.terminate` (priority `-4096`), stops and flushes to the configured sink.

On each **console command** (opt-in: `profile_console: true`):

1. Starts on `console.command` (priority `4096`) with `command=<name>` (never argv).
2. On `console.terminate` (priority `-4096`), stops and flushes.

On each **consumed Messenger message** (opt-in: `profile_messenger: true`, needs `symfony/messenger`):

1. Middleware starts only when the envelope has a `ReceivedStamp` (worker path), with
   `message=<FQCN>` (never the payload). Sync `dispatch()` inside HTTP is left to the request profile.
2. Stops and flushes in `finally` after the rest of the bus stack.

HTTP is profiled by default; console and Messenger stay off until enabled. For
`messenger:consume`, prefer `profile_messenger: true`. If both flags are on, the console
subscriber skips `messenger:consume` / `messenger:consume-messages` so the long-lived consumer
command does not share one unit of work with every message.

One shared `UnitOfWorkProfiler` is built per worker process.
Each request or consumed message starts a separate unit of work on that same profiler;
the bundle stops and flushes it at the lifecycle boundaries above, including in long-lived
workers. These calls also publish the core's deferred diagnostics outside sampling callbacks.

**Fail-open (runtime):** listener/middleware boundaries swallow errors; factory construction
failures (including a broken logger or an unknown `sink` after env resolve) return a no-op
profiler so the worker keeps serving. **Not fail-open:** invalid YAML / config-tree values still
fail at container build / `cache:warmup` — that is intentional so a half-wired profiler never
reaches request handling. Missing `%env(...)%` values also fail when Symfony resolves service
arguments, before the factory `try/catch`.

Inspect **this process** counters (collected / dropped / delivered / inactive sampler, …):

```bash
bin/console tracewick:stats
```

That command starts a new CLI process, so it shows zeros unless something profiled **in that
same process**. FPM / RoadRunner / `messenger:consume` counters live in those workers —
use `HttpProfiler::diagnostics()` from app code there, or scrape host metrics from core when
configured. Same snapshot API is available as `HttpProfiler::diagnostics()`.

## Production path

- Prefer **`SpoolSink`** (`sink: spool`) from the request worker.
- Deliver spool files with
  [`tracewick-drain`](https://github.com/tracewick/tracewick/blob/main/docs/drain.md) or the
  [Go agent](https://github.com/tracewick/agent) beside the app.
- Direct `sink: pyroscope` from PHP-FPM is for experiments only — not the default.

```bash
vendor/bin/tracewick-drain \
  --spool-dir=/var/spool/tracewick \
  --pyroscope-url=http://127.0.0.1:4040
```

Bundle configuration is resolved by Symfony's `%env(...)%` mechanism. The standalone
`tracewick-drain` and Go agent read their own process environment and CLI arguments;
they do not boot Symfony or load the application's `.env`. Export variables into the
reader process through your service manager or container configuration. Either reader
must share the bundle's spool directory or volume, with compatible filesystem permissions;
run one normal reader per spool.

Reader settings (aggregation, limits, sleep, authentication) belong to that reader,
not to `tracewick.yaml`. Consult the documentation for your installed core or agent
version for supported environment variables and CLI precedence.

Put the spool on a **local** volume or tmpfs. Details:
[core runtime](https://github.com/tracewick/tracewick/blob/main/docs/runtime.md),
[drain](https://github.com/tracewick/tracewick/blob/main/docs/drain.md).

## Kill switch

Turn profiling off without a deploy:

| Layer | How |
|---|---|
| Bundle | `tracewick.enabled: false` (no subscriber / profiler services) |
| Core | `TRACEWICK_DISABLE_FILE` — if the path exists, profiling is off |
| Core | `TRACEWICK_ENABLED=0` / `false` / `off` / `no` |

Core kill switches still apply when the bundle is enabled. See
[`docs/runtime.md`](https://github.com/tracewick/tracewick/blob/main/docs/runtime.md).

Without **ext-excimer**, Tracewick is a no-op (warn once, then idle). The app keeps serving.

## Development

```bash
composer install
make ci
make infection   # needs the Infection PHAR (same version as CI)
```

## License

Apache License 2.0. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
