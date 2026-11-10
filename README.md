# Tracewick Symfony Bundle

[![CI](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/tracewick/symfony-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/tracewick/symfony-bundle)

> [!WARNING]
> Tracewick Symfony Bundle **0.1.x is alpha**. The public API is not stable until 1.0.0 — expect
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

```bash
composer require tracewick/symfony-bundle
```

If Packagist does not list the package yet, pin `dev-develop` from GitHub until the first tag is published.

Install the extension separately when you want samples (prefer [PIE](https://php.github.io/pie/)):

```bash
pie install wikimedia/excimer
php -m | grep -i excimer
```

Enable the bundle if Flex does not:

```php
// config/bundles.php
return [
    // ...
    Tracewick\Bridge\Symfony\TracewickBundle::class => ['all' => true],
];
```

Copy the example config into the app (Flex recipe comes later):

```bash
cp vendor/tracewick/symfony-bundle/config/packages/tracewick.yaml config/packages/tracewick.yaml
```

Set at least the application name and spool directory (env defaults are in the example file):

```yaml
# config/packages/tracewick.yaml (excerpt)
tracewick:
    enabled: true
    application_name: '%env(TRACEWICK_APPLICATION)%'
    sample_rate: 0.1
    spool_directory: '%env(TRACEWICK_SPOOL_DIR)%'
    sink: spool
    base_labels:
        env: '%kernel.environment%'
```

Full example with comments:
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
`messenger:consume`, prefer `profile_messenger: true` and leave `profile_console` false so the
long-lived consumer command does not share one unit of work with every message.

One shared `UnitOfWorkProfiler` is built per worker process. Failures are swallowed at the
listener and factory boundary (fail-open): bad config or a missing extension disables profiling
for that worker instead of breaking the request.

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
