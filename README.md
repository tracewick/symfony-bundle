# Tracewick Symfony Bundle

[![CI](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/tracewick/symfony-bundle/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/tracewick/symfony-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/tracewick/symfony-bundle)

> **Early development — not for production.**
>
> This package is unfinished public scaffolding. There is **no** tagged release yet, the HTTP
> subscriber / config tree are still in progress, and the API may change without notice. Do not
> depend on it in real apps until `0.1.0`. Prefer the hand-rolled Demo wiring in
> [`tracewick/tracewick`](https://github.com/tracewick/tracewick) until then.

Official Symfony integration for
[`tracewick/tracewick`](https://github.com/tracewick/tracewick) — a PHP sampling profiler on
ext-excimer that writes profiles to a local spool (recommended) or optionally to Grafana
Pyroscope.

## Requirements

| | |
|---|---|
| PHP | 8.2–8.5 |
| Symfony | 6.4 / 7.x / 8.x |
| Tracewick | `^0.2` |
| ext-excimer | suggested (no-op without it) |

Symfony 6.4 on PHP 8.1 is not supported (same PHP floor as the core library).

## Install (preview)

```bash
composer require tracewick/symfony-bundle:dev-develop
```

Enable the bundle if Flex does not do it for you:

```php
// config/bundles.php
return [
    // ...
    Tracewick\Bridge\Symfony\TracewickBundle::class => ['all' => true],
];
```

Full YAML defaults and the HTTP subscriber ship in a follow-up. Until then, see the hand-rolled
Demo wiring in the core repository
(`docker/reference-app/overlay/src/EventSubscriber/TracewickSubscriber.php`).

## Production path

- Prefer **`SpoolSink`** from the request worker.
- Run [`tracewick-drain`](https://github.com/tracewick/tracewick/blob/main/docs/drain.md) or the
  [Go agent](https://github.com/tracewick/agent) beside the app to deliver spool files to
  Pyroscope.
- Direct `PyroscopeSink` from FPM is for experiments only.

## Kill switch

Honoured by Tracewick core (see [`docs/runtime.md`](https://github.com/tracewick/tracewick/blob/main/docs/runtime.md)):

- `TRACEWICK_DISABLE_FILE` — if the path exists, profiling is off
- `TRACEWICK_ENABLED` — `0` / `false` / `off` / `no` disables

## Development

```bash
composer install
make ci
```

## License

Apache License 2.0. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
