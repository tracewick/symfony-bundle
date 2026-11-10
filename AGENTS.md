# AGENTS.md

Instructions for AI coding agents working on this repository. Humans should read
[CONTRIBUTING.md](CONTRIBUTING.md); everything there applies to agents too.

## Project

`tracewick/symfony-bundle` (namespace `Tracewick\Bridge\Symfony\`) is the official Symfony
integration for [`tracewick/tracewick`](https://github.com/tracewick/tracewick). It must use
Runtime and Sink APIs only. Do not copy code from Excimetry or any other profiler (see
[NOTICE](NOTICE)).

Canon for HTTP lifecycle and defaults: the core Demo subscriber under
`docker/reference-app/overlay/` and `docs/runtime.md` in the core repository. Prefer that
behaviour over inventing new semantics.

## Commands

```bash
composer install
make ci          # lint, static analysis, unit tests, composer audit
make infection   # mutation testing (Infection PHAR; MSI ≥ 80%)
make cs-fix      # fix coding style and normalize composer.json
make stan        # PHPStan, level max
make test-unit   # unit tests (no ext-excimer required)
```

**Before every commit**, run locally what CI would catch on this change. Prefer `make ci`.
Do not push blind and fix style/stan/rector on GitHub.

## Layout

- `src/` bundle code, PSR-4 `Tracewick\Bridge\Symfony\`.
- `tests/Unit/` tests without ext-excimer; PSR-4 `Tracewick\Bridge\Symfony\Tests\`.

## Rules

- **Fail-open.** Catch at the subscriber/listener boundary when needed. Bad config should disable
  profiling, not break boot.
- Every PHP file declares `strict_types=1`. Classes are `final` unless designed for extension.
- PHPStan runs at level `max`. Do not add baseline entries or ignores without a justifying comment.
- Never skip, disable, or delete a test to make CI green. Fix the cause.
- Write real tests with the change. Unit tests must pass without ext-excimer.
- Keep runtime `require` minimal: HttpKernel + DI + Config + Tracewick. FrameworkBundle belongs
  in `require-dev` only.
- Default sink = spool; default `sampleRate` = `0.1`; labels = route name (not raw path).
- English only. Conventional Commits. No AI co-authorship trailers.
- Follow git flow: `feature/*` → PR into `develop`. Never push to `main` or `develop` directly.
- Do not edit `CHANGELOG.md` in a feature branch; release-please owns it.
- After opening or pushing a PR, babysit checks and review threads until review-ready or blocked.
  Treat `codecov/project` and `codecov/patch` as first-class gates when present.
