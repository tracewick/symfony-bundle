# Core 1.0 installation preflight

This is a source preflight, not an announcement that an RC exists on Packagist. Composer path
repositories assign local package versions without creating tags. Repeat the clean-install and
upgrade checks against the actual distribution after publication.

## Candidate inputs

- Core: `37e5928506cee2b1c395c2c8d01e89f2bf43a6b8` (develop after core PR #89).
- Bundle: `d49c7618bbf3850a3923d26eb8763bd7bfa34a62`, with only its core requirement widened
  from `^0.2` to `^0.2 || ^1.0`. Runtime sources are unchanged. Use the reviewed bundle commit
  containing this change when repeating the check.
- Both local packages are labelled `1.0.0-rc.1` for dependency-resolution testing only.
- Published upgrade baseline: core `v0.2.0` (`30ddf9685a0a65066c38b250466350fc19431041`) and
  bundle `v0.2.0` (`3bcb7988fcd9c1673eed1d7afabc8fe056c9c1d0`).

## Reproduce

Use a disposable directory containing `core/`, `bundle/` and the test application. Export pinned
source snapshots with `git archive <commit>`, rather than copying vendor directories. Keep the
bundle's proposed requirement intact; do not label the new core as a 0.2 version.

Run PHP with Excimer disabled, verify `extension_loaded('excimer') === false`, and record
`php --version` and `composer --version`. Keep normal Composer platform and security checks enabled.
The local path snapshots must remain available until the test is complete.

Create a real Symfony application, repeating with `6.4.*`, `7.4.*` and `8.1.*`:

```bash
composer create-project 'symfony/skeleton:6.4.*' app --no-interaction
cd app
```

For the upgrade case, first install the published baseline:

```bash
composer require tracewick/symfony-bundle:0.2.0 tracewick/tracewick:0.2.0 --no-interaction
```

Register `Tracewick\Bridge\Symfony\TracewickBundle` in `config/bundles.php` for all environments.
Add the following application configuration and load the variables through the skeleton's Dotenv
bootstrap. Keep the application configuration through the upgrade:

```yaml
# config/packages/tracewick.yaml
tracewick:
    application_name: '%env(TRACEWICK_APPLICATION)%'
    spool_directory: '%env(TRACEWICK_SPOOL_DIR)%'
    profile_console: true
```

```dotenv
TRACEWICK_APPLICATION=preflight-app
TRACEWICK_SPOOL_DIR=var/spool/tracewick
```

This deliberately keeps the default spool sink and sample rate 0.1, while opting into console
profiling. Warm the container and exercise the application before upgrading. Save the baseline
lock and hashes of `.env`, `config/` and `src/`.

In the disposable application's `composer.json`, configure these repositories (paths are relative
to the application):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../core",
            "options": {
                "symlink": false,
                "reference": "config",
                "versions": {"tracewick/tracewick": "1.0.0-rc.1"}
            }
        },
        {
            "type": "path",
            "url": "../bundle",
            "options": {
                "symlink": false,
                "reference": "config",
                "versions": {"tracewick/symfony-bundle": "1.0.0-rc.1"}
            }
        }
    ]
}
```

These entries belong to the application, not to the distributed bundle. Require both exact
candidate versions in the application; their explicit prerelease versions grant the root-level
stability approval. Leave `minimum-stability: stable` and `prefer-stable: true` intact:

```bash
composer require tracewick/symfony-bundle:1.0.0-rc.1 tracewick/tracewick:1.0.0-rc.1 --no-update
composer update tracewick/symfony-bundle tracewick/tracewick --with-all-dependencies --no-interaction
```

For a clean candidate installation, use a separate fresh skeleton with no Tracewick baseline.
Configure the repositories and exact requirements before installing the candidate packages.
In the recorded clean runs the application had no vendor directory or lock when `composer install`
resolved and installed its dependencies. In upgrade runs both were retained and the targeted
update above was used. Inspect the resulting lock: both Tracewick entries must be `1.0.0-rc.1`
with path distributions, not a silently retained published 0.2 package.

Run the normal application commands in production mode:

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
APP_ENV=prod APP_DEBUG=0 php bin/console lint:container
APP_ENV=prod APP_DEBUG=0 php bin/console tracewick:stats
composer check-platform-reqs
composer audit
```

For HTTP, add two named routes with different paths and a controller returning a fixed 200 response.
Boot one `App\Kernel('prod', false)`, handle and terminate both requests on that kernel, and check
status and body. Expose a public application alias for `Profiler\HttpProfiler` to `tracewick.profiler`
for this probe. Verify that both requests use the same `UnitOfWorkHttpProfiler`, that resolved
configuration still has `sink=spool` and `sample_rate=0.1`, and that diagnostics report
`NullSamplerReason::ExtensionMissing`, zero swallowed errors and zero sampler failures. This checks
that fail-open behavior has not hidden a factory fallback to `NullHttpProfiler`.

Compare the saved application-file hashes after update. Run the bundle's existing unit/functional
suite against the candidate core source, still without Excimer; its recording-profiler tests check
lifecycle call order and route labels. Missing-extension tests establish installation and bridge
behavior, not sample collection or delivery accuracy.

Negative control: use the unchanged bundle requirement `^0.2` with the core path package labelled
`1.0.0-rc.1`; Composer must reject the pair. No aliases, platform ignores or lowered stability floor
are needed to make the corrected pair work.

## Recorded evidence, 2026-11-18

Runtime: Linux, PHP 8.5.12, Composer 2.11.2, Excimer explicitly disabled. Each row has a separate
clean installation and a baseline-to-candidate upgrade, using real Symfony skeletons and normal
Composer scripts. This is not a full PHP/Symfony cross-product certification.

| FrameworkBundle | Clean candidate | Upgrade from published 0.2.0 pair | Application config |
| --- | --- | --- | --- |
| 6.4.49 | Pass | Pass | Unchanged |
| 7.4.23 | Pass | Pass | Unchanged |
| 8.1.11 | Pass | Pass | Unchanged |

All six candidate smoke runs pass: production cache clear/warmup, container lint, stats command,
two sequential HTTP requests, real core-profiler construction and missing-extension diagnostics.
Application `.env`, config and source hashes match before/after candidate installation or update.
The unchanged `^0.2` requirement is rejected as expected. The existing bundle suite against the
candidate core passes: 83 tests, 239 assertions, one existing optional Excimer skip.
Local style, Rector, PHPStan, dependency analysis, the ordinary 83-test suite and PCOV also pass.
The separate local `composer audit` is blocked by a proxy HTTP 403 at the Packagist security
advisories endpoint; no audit settings were weakened.

## Flex recipe

[Recipe PR #2116](https://github.com/symfony/recipes-contrib/pull/2116), head
`dd4f839742ee85983f24e2f1ed918c9157fe0c90`, is still open. Its QA on 2026-11-10 fails inside the
checker dependency `Symfony/HttpClient/Internal/CurlClientState.php` at
`curl_multi_setopt(CURLMOPT_PUSHFUNCTION)`; the updated-recipe installation job is skipped.
[Failed job](https://github.com/symfony/recipes-contrib/actions/runs/42827960917/job/129870137317).
The checker crash occurs before a usable installation result; it neither establishes a recipe
configuration defect nor proves that the recipe installs successfully.

The application probes above register the bundle and apply the minimal config/env values manually,
with the same values as the proposed recipe and an additional application console opt-in. They do
not claim that Flex installed the pending recipe. A successful upstream recipe run, automatic
recipe installation/update and installation from real Packagist RC tags remain separate checks.
No upstream rerun, review comment or recipe changes were made during this preflight.
