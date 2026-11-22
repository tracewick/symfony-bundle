# Core 1.0 RC installation preflight

Core **v1.0.0-rc.1 is published on Packagist**. This check installs that distribution with an
archive of the proposed bundle change in PR #25. The bundle archive uses its real development
branch version; it is not labelled as an already published bundle RC. After the bundle release,
repeat the clean-install and upgrade checks with both actual Packagist packages.

## Recorded inputs

- Core: Packagist `v1.0.0-rc.1`, source `110a65061c2ee61c4f44e09767cd7ee2e53b2a76`.
  There is no local core repository, version override, alias or platform bypass.
- Bundle archive: `882df76ab232bcb6e6dbdb87b8e8a66c8d16ca18`, PR #25 after merging current
  develop (`7928d3e`). Package version: `dev-feature/rc-installation-preflight`.
  Its unmodified Composer metadata requires core `^0.2 || ^1.0`; runtime/config sources
  are unchanged. Subsequent report-only edits do not change these tested inputs.
- Bundle ZIP SHA-256: `1999c42354e53ca0284976bf0adb5ca569ffde8d1e912aad46df891b9574f1c1`.
- Published upgrade baseline: core `v0.2.0` (`30ddf9685a0a65066c38b250466350fc19431041`)
  and bundle `v0.2.0` (`3bcb7988fcd9c1673eed1d7afabc8fe056c9c1d0`).

The 18 November check used local core sources before publication. The check below supersedes
that evidence for core installation; it does not announce a bundle release or a final release SHA.

## Reproduce

Use PHP without Excimer and verify `extension_loaded('excimer') === false`. Record PHP and
Composer versions. Keep normal platform checks, security checks, `minimum-stability: stable`
and `prefer-stable: true`; explicit root requirements admit only the selected development/RC packages.

From the bundle checkout, export the reviewed commit to a disposable directory. The recorded
commit above is reproducible from this PR's history; use the commit under review for a new run:

```bash
BUNDLE_REF=882df76ab232bcb6e6dbdb87b8e8a66c8d16ca18
WORK_DIR=$(mktemp -d)
git archive --format=zip "$BUNDLE_REF" -o "$WORK_DIR/bundle-candidate.zip"
python3 - "$WORK_DIR/bundle-candidate.zip" "$BUNDLE_REF" > "$WORK_DIR/bundle-package.json" <<'PY'
import hashlib, json, pathlib, sys, zipfile
archive = pathlib.Path(sys.argv[1]).resolve()
with zipfile.ZipFile(archive) as stream:
    package = json.loads(stream.read('composer.json'))
package['version'] = 'dev-feature/rc-installation-preflight'
package['source'] = {'type': 'git', 'url': 'https://github.com/tracewick/symfony-bundle.git',
                     'reference': sys.argv[2]}
package['dist'] = {'type': 'zip', 'url': archive.as_uri(), 'reference': sys.argv[2],
                   'shasum': hashlib.sha1(archive.read_bytes()).hexdigest()}
print(json.dumps(package, indent=2))
PY
```

This describes the actual exported bundle metadata and files. It creates no tag and supplies
no replacement for the core package. Keep the archive until installation checks finish.

Create separate Symfony skeletons for clean installation and upgrade, repeating with
`6.4.*`, `7.4.*` and `8.1.*`:

```bash
cd "$WORK_DIR"
composer create-project 'symfony/skeleton:6.4.*' clean-6.4 --no-interaction
composer create-project 'symfony/skeleton:6.4.*' upgrade-6.4 --no-interaction
```

For the upgrade fixture, first install the real published baseline before adding the candidate
repository. Keep this vendor directory and lock for the targeted update:

```bash
cd "$WORK_DIR/upgrade-6.4"
composer require tracewick/symfony-bundle:0.2.0 tracewick/tracewick:0.2.0 --no-interaction
```

Register `Tracewick\Bridge\Symfony\TracewickBundle` in `config/bundles.php` for all environments.
Configure the application in both fixtures and retain these files across the upgrade:

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

Load these variables through the skeleton's normal Dotenv bootstrap. The default spool sink,
sample rate 0.1 and route-name labels remain in effect; only console profiling is opted in.
Warm and exercise the baseline upgrade fixture, then save hashes of `.env`, `config/` and `src/`.
For the clean fixture, remove only its initial skeleton vendor directory and lock before
candidate resolution, and save the same application hashes.

In each fixture's `composer.json`, add only the bundle artifact repository. When the fixtures
are direct children of the disposable directory, run this from the fixture root:

```bash
python3 - <<'PY'
import json, pathlib
path = pathlib.Path('composer.json')
root = json.loads(path.read_text())
package = json.loads(pathlib.Path('../bundle-package.json').read_text())
root['repositories'] = [{'type': 'package', 'package': package}]
root['require']['tracewick/symfony-bundle'] = 'dev-feature/rc-installation-preflight'
root['require']['tracewick/tracewick'] = '1.0.0-rc.1'
path.write_text(json.dumps(root, indent=4) + '\n')
PY
```

For clean installation run `composer install --prefer-dist --no-interaction` with no vendor/lock.
For upgrade run:

```bash
composer update tracewick/symfony-bundle tracewick/tracewick --with-all-dependencies --prefer-dist --no-interaction
```

Normal Composer post-install/update scripts must succeed. Inspect the resulting lock:
core must be `v1.0.0-rc.1` with its published source reference and GitHub ZIP distribution;
bundle must be the selected development version with the archive's source reference and checksum.
Compare installed bundle files to the archive, not merely the declared version strings.

Run the normal application commands:

```bash
export APP_ENV=prod APP_DEBUG=0
php bin/console cache:clear
php bin/console cache:warmup
php bin/console lint:container
php bin/console tracewick:stats
composer check-platform-reqs
composer audit
```

For HTTP, add two named routes and a controller returning a fixed 200 response. Expose a public
application alias from `Profiler\HttpProfiler` to `tracewick.profiler`. Boot one production kernel,
handle and terminate both requests, and verify the same `UnitOfWorkHttpProfiler` is reused.
Check spool/sample defaults, `NullSamplerReason::ExtensionMissing`, zero swallowed errors and
zero sampler failures. This catches a factory silently falling back to `NullHttpProfiler`.
Compare application-file hashes after installation/update.

Also run the existing bundle suite against the published core RC, without Excimer:

```bash
composer update 'tracewick/tracewick:1.0.0-rc.1' --with-all-dependencies
composer ci
composer test:coverage
```

The temporary update constraint selects the RC without changing the bundle's public requirement.
The normal dependency resolution and CI matrix retain coverage of the supported 0.2 line.

Negative control: in a separate application require the published bundle `0.2.0` and core
`1.0.0-rc.1` with no candidate repository. Composer must reject the pair because that bundle
requires core `^0.2`. Do not use aliases to disguise the RC as 0.2.

## Recorded evidence, 2026-11-23

Linux, PHP 8.5.12, Composer 2.11.2; Excimer explicitly disabled. All six fixtures use actual
Symfony skeletons and normal Composer scripts. This is not the full PHP/Symfony cross-product.

| FrameworkBundle | Clean installation | Upgrade from published 0.2.0 pair | Application config/source |
| --- | --- | --- | --- |
| 6.4.49 | Pass | Pass | Unchanged |
| 7.4.23 | Pass | Pass | Unchanged |
| 8.1.11 | Pass | Pass | Unchanged |

All production cache clear/warmup, container lint, stats, platform checks and sequential HTTP
probes pass. Installed core source is the published `110a650`; installed bundle files match
the exported archive. There are no local core repositories or version aliases. The published
0.2.0 bundle/core RC negative control is rejected as expected.

The ordinary suite with core 0.2 and the suite with the published core RC each pass:
83 tests, 239 assertions, one existing optional Excimer skip. Local style, Rector, PHPStan,
dependency analysis and PCOV pass; covered executable statements are 329/331 (99.40%).
Infection against the published core RC kills all 245 non-ignored mutants (25 existing ignores),
MSI 100%; thresholds and ignores are unchanged. Missing-extension tests verify bridge behaviour,
not native sampling or profile-delivery accuracy.

Standalone local security-advisory lookup is blocked by the environment proxy's HTTP 403;
no audit settings were weakened. Hosted CI audits remain required. After the bundle RC is
published, repeat package installation with both real Packagist distributions and verify its
actual signed release assets separately.

## Flex recipe

[Recipe PR #2116](https://github.com/symfony/recipes-contrib/pull/2116) remains open and unmerged.
Its recorded QA fails inside the checker dependency Symfony HttpClient at
`curl_multi_setopt(CURLMOPT_PUSHFUNCTION)`; updated-recipe installation is skipped
([job](https://github.com/symfony/recipes-contrib/actions/runs/42827960917/job/129870137317)).
The maintainer confirmed a repository-wide upstream CI problem; this is a separate external wait.

The fixtures register the bundle and apply equivalent minimal config/env values manually.
They do not claim that Flex installed the pending recipe. No upstream rerun, message or recipe
change is needed for this preflight. Automatic recipe installation remains unverified until
upstream review/CI/merge completes.
