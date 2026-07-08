# Operational Runbook

Day-to-day operations for **`wonsulting/opensearch-scout-driver`**: how to install it, run OpenSearch
locally, run the test/quality suite, map local commands to CI, consume the package from an application,
and cut a release. For how the code is structured, see [architecture.md](architecture.md).

> **Verification status.** Every command below was executed on 2026-07-08 while writing this runbook, on
> macOS with PHP 8.4.23 (Laravel Herd), Composer 2.10.1, Docker 29.6.1, GNU Make 3.81. The OpenSearch /
> Docker commands are verified working and their real output is shown. The Composer install-and-test path
> is **currently blocked** by stale dev dependencies — see the banner in
> [Install dependencies](#1-install-dependencies) and [Known issues & discrepancies](#known-issues--discrepancies).

## Prerequisites

| Requirement | Notes |
|---|---|
| **PHP 8.2+** | Per the README's declared support (Laravel 11.x–13.x / Scout 10.x–11.x). ⚠️ `composer.json` currently declares a looser `php: ^7.4 \|\| ^8.0` and CI tests 7.4–8.2 — see [Known issues](#known-issues--discrepancies). |
| **Composer 2.x** | `composer:v2` is what CI uses. |
| **A reachable OpenSearch 2.x at `localhost:9200`** | Start one locally with `make up wait` — see [Run OpenSearch locally](#run-opensearch-locally). Required for the test suite (all tests are integration tests that hit a live cluster). |
| **Docker** | Only needed for the local OpenSearch container. |
| **make**, **curl** | Used by the `Makefile` helpers. |

Verify the toolchain:

```console
$ php --version
PHP 8.4.23 (cli) (built: Jul  6 2026 06:39:02) (NTS clang 15.0.0)

$ composer --version
Composer version 2.10.1 2026-06-04 10:25:59

$ docker --version
Docker version 29.6.1, build 8900f1d
```

## 1. Install dependencies

> ### ⚠️ Currently blocked
>
> `composer install` **does not resolve** on the repository as committed. The `require-dev` pin
> `orchestra/testbench: ^7.5` only satisfies Laravel **9.x**, and every `laravel/framework` 9.x release
> is blocked by published security advisories under Composer's default policy:
>
> ```console
> $ composer install
> No composer.lock file present. Updating dependencies to latest instead of installing from lock file.
> Loading composer repositories with package information
> Updating dependencies
> Your requirements could not be resolved to an installable set of packages.
>
>   Problem 1
>     - Root composer.json requires orchestra/testbench ^7.5 -> satisfiable by orchestra/testbench[v7.5.0, ..., v7.56.0].
>     - orchestra/testbench[...] require laravel/framework ^9.x -> found laravel/framework[v9.x]
>       but these were not loaded, because they are affected by security advisories
>       ("PKSA-m5cs-t1y6-qpcs", "PKSA-3r5d-mb8f-1qw9", ...).
> ```
>
> Because no `vendor/` directory is produced, **the `composer test` / `test-coverage` / `check-style` /
> `analyse` commands below cannot run locally as-is.** They are documented here so the runbook is
> complete once the toolchain is modernized. See [Known issues & discrepancies](#known-issues--discrepancies)
> for the fix and follow-up.
>
> **CI is unaffected** because it does not run `composer install` — `test.yml` overrides the pins per
> matrix leg with `composer require --with-all-dependencies orchestra/testbench:^{version} …` (see the
> [CI ↔ script mapping](#ci--script-mapping)). To reproduce a green environment locally today, mirror
> a CI leg, e.g.:
>
> ```bash
> # e.g. the PHP 8.2 CI leg (Laravel 10 era) — writes to composer.json, so do it on a throwaway checkout
> composer require --dev --with-all-dependencies \
>     orchestra/testbench:^8.5 phpunit/phpunit:^10.1 laravel/scout:^10.0
> ```

Once the dev dependencies are modernized, the normal first step is:

```bash
composer install
```

`composer.lock` is git-ignored, so dependency resolution happens fresh on every install.

## 2. Test & quality scripts

All scripts are defined in `composer.json`. Each has a matching `Makefile` target (which just wraps the
`composer` script, plus colored logging).

| Command | Underlying tool | Config | Make target | Needs live OpenSearch? |
|---|---|---|---|---|
| `composer test` | `phpunit --testdox` | `phpunit.xml.dist` | `make test` | **Yes** |
| `composer test-coverage` | `phpunit --testdox --coverage-text` | `phpunit.xml.dist` | `make coverage` | **Yes** |
| `composer check-style` | `php-cs-fixer fix --dry-run --diff` | `.php-cs-fixer.dist.php` | `make style-check` | No |
| `composer fix-style` | `php-cs-fixer fix` (applies changes) | `.php-cs-fixer.dist.php` | — (no target) | No |
| `composer analyse` | `phpstan analyse` (level `max`, `src` only) | `phpstan.neon.dist` | `make static-analysis` | No |

Notes:

- **Coverage** requires Xdebug in coverage mode. `make coverage` sets it for you
  (`XDEBUG_MODE=coverage composer test-coverage`); if you call the composer script directly, prefix it
  the same way.
- **The whole test suite is integration tests.** `phpunit.xml.dist` defines a single suite named
  `integration` pointing at `tests/Integration`; the base `TestCase` runs `opensearch:migrate` in
  `setUp()` and sets `refresh_documents => true`, so a reachable cluster at `localhost:9200` is
  mandatory. The relational side uses in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- `phpunit.xml.dist` sets `OPENSEARCH_HOST=localhost:9200`, which matches the port the `Makefile`
  container publishes — no extra env override is needed when you use `make up`.
- `test-coverage`/`coverage` and `fix-style` have **no CI job**; they are local-only conveniences.

> ⚠️ These commands are blocked today — see [§1](#1-install-dependencies). Once `composer install`
> succeeds they run as documented.

## Run OpenSearch locally

The `Makefile` runs a single-node OpenSearch 2.x container (image `opensearchproject/opensearch:2`, host
port 9200, security disabled) with no `docker-compose` needed.

```bash
make up wait   # start the container, then block until the cluster is healthy
make down      # stop (and, via --rm, remove) the container
```

`make up wait` runs two targets in sequence: `up` launches the container detached and returns
immediately; `wait` polls the cluster health endpoint every 5 seconds until it reports `yellow` or
better. Verified output:

```console
$ make up wait
→ Starting opensearch-scout-driver-opensearch container
afabca5afcc81c3a97a0f179cb298140f0775b1dc895a26bf13e8bd0996c6777
✔︎ opensearch-scout-driver-opensearch is started
→ Waiting for opensearch-scout-driver-opensearch container
curl: (56) Recv failure: Connection reset by peer
✘ opensearch-scout-driver-opensearch is not ready, waiting...
{"cluster_name":"docker-cluster","status":"green","timed_out":false,"number_of_nodes":1, ... }
✔︎ opensearch-scout-driver-opensearch is ready
```

The `curl: (56) Recv failure` lines are expected — they are the `wait` loop retrying while OpenSearch
boots. Once you see `status":"green"` (or `yellow`) the cluster is ready.

Confirm the version and that it is OpenSearch 2.x:

```console
$ curl -s localhost:9200 | grep -E '"distribution"|"number"'
    "distribution" : "opensearch",
    "number" : "2.19.6",
```

Stop it when finished:

```console
$ make down
→ Stopping containers
opensearch-scout-driver-opensearch
✔︎ Containers are stopped
```

### Raw `docker run` equivalent

If you prefer not to use `make`, the `up` target runs exactly this (image tag overridable via
`OPENSEARCH_VERSION`):

```bash
docker run --rm -d \
    --name opensearch-scout-driver-opensearch \
    -p 9200:9200 \
    -e discovery.type=single-node \
    -e DISABLE_SECURITY_PLUGIN=true \
    -e DISABLE_INSTALL_DEMO_CONFIG=true \
    opensearchproject/opensearch:2
```

> **Note on the security env var.** This uses the OpenSearch 2.x Docker image's own
> `DISABLE_SECURITY_PLUGIN=true` (plus `DISABLE_INSTALL_DEMO_CONFIG=true`). Some older docs reference
> `-e plugins.security.disabled=true` instead; the `Makefile` above is the source of truth for this
> repo.

### Full local dev loop

```bash
make up wait      # 1. start OpenSearch, wait for health
composer test     # 2. run the suite (once §1 is unblocked)  — or: make test
make down         # 3. stop OpenSearch
```

## CI ↔ script mapping

CI lives in `.github/workflows/`. The three build workflows trigger on `push` to any branch except
`master` (ignoring `*.*` tags).

| Workflow (job) | PHP | OpenSearch in CI | Command it runs | Local equivalent |
|---|---|---|---|---|
| `test.yml` (`test`) | matrix 7.4 / 8.0 / 8.1 / 8.2 | `ankane/setup-opensearch@v1` (v2.5, on `localhost:9200`) | `composer test` | `make test` |
| `code-style.yml` (`style-check`) | 8.0 | — | `composer check-style` | `make style-check` |
| `static-analysis.yml` (`static-analysis`) | 8.0 | — | `composer analyse` | `make static-analysis` |
| — (no CI job) | — | — | `composer test-coverage`, `composer fix-style` | `make coverage` |

`stale.yml` is a scheduled housekeeping workflow (marks/closes stale issues); it is not part of the
build.

Key differences between CI and a local run:

- **Dependency install.** `test.yml` does **not** use `composer install`. It installs per-matrix with
  `composer require --no-interaction --with-all-dependencies orchestra/testbench:^{testbench}
  phpunit/phpunit:^{phpunit} laravel/scout:^{scout}`, pinning each PHP leg (e.g. 8.2 → testbench 8.5 /
  Scout 10 / PHPUnit 10.1). `code-style.yml` and `static-analysis.yml` **do** use
  `composer install --no-interaction` — which is why those two workflows are also affected by the §1
  blocker if the pins aren't updated.
- **OpenSearch.** CI uses the `ankane/setup-opensearch` action rather than the `Makefile` container, but
  it lands on the same `localhost:9200`.

## Consuming the package from an application (Composer path repository)

To develop this package against a host application (e.g. the WonsultingAI app) without publishing,
add a `path` repository to the **application's** `composer.json` pointing at your local checkout:

```jsonc
// application composer.json
"repositories": [
    {
        "type": "path",
        "url": "../opensearch-scout-driver"   // path to this checkout
    }
]
```

Then require it at the dev version (Composer symlinks the path):

```bash
composer require wonsulting/opensearch-scout-driver:@dev
```

Wire it up in the application the same way as a normal install (see the README's Installation section):

1. Ensure `laravel/scout` is installed and its config is published.
2. Set the driver: `'driver' => env('SCOUT_DRIVER', 'opensearch')` in `config/scout.php`
   (or `SCOUT_DRIVER=opensearch` in `.env`).
3. Publish and configure the **client** connection (host/port/auth) — this driver relies on
   `wonsulting/opensearch-client`:
   `php artisan vendor:publish --provider="OpenSearch\Laravel\Client\ServiceProvider"`.
4. Optionally publish this driver's own config to set `refresh_documents`:
   `php artisan vendor:publish --provider="OpenSearch\ScoutDriver\ServiceProvider"`.

## Release process

The package is distributed via Composer (package name `wonsulting/opensearch-scout-driver`). Releases
follow [semantic versioning](https://semver.org/):

1. Ensure `master` is green (tests, code style, static analysis) and the changelog/README reflect the
   change.
2. Choose the version per semver: **major** for breaking changes (e.g. dropping PHP/Laravel versions or
   changing a public contract), **minor** for backward-compatible features, **patch** for fixes.
3. Tag and push:
   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin vX.Y.Z
   ```
4. Packagist publishes the new version from the tag (via the repository's Packagist webhook / auto-update
   integration). Confirm the version appears on the package's Packagist page.

> The modernization initiative targets **v3.0.0** as the first modernized release (PHP 8.2+ / Laravel
> 11–13). Coordinate the version bump with the dependency/CI modernization tracked as a follow-up (see
> below).

## Known issues & discrepancies

1. **`composer install` is broken on the committed repo (blocking).** `require-dev` pins
   `orchestra/testbench: ^7.5` (Laravel 9), whose `laravel/framework` releases are all blocked by
   security advisories under modern Composer, so no `vendor/` can be produced locally. This blocks
   `composer test` / `test-coverage` / `check-style` / `analyse` and the `code-style` / `static-analysis`
   CI workflows (which use `composer install`). **Fix / follow-up:** modernize the dev dependencies to
   match the README's declared support — bump `orchestra/testbench` to `^9`/`^10`, `laravel/scout` to
   `^11`, `phpunit/phpunit` to `^10`/`^11`, and update the `test.yml` PHP matrix (currently 7.4–8.2)
   accordingly. This should be its own ticket in the modernization initiative; it is intentionally out
   of scope for this documentation change.

2. **PHP / framework version matrix is inconsistent across the repo.** `README.md` declares PHP 8.2+ /
   Laravel 11.x–13.x / Scout 10.x–11.x, but `composer.json` still declares `php: ^7.4 || ^8.0` with
   Laravel-9-era dev pins, and `test.yml` still tests PHP 7.4–8.2. Treat the README as the intended
   target; the `composer.json`/CI constraints lag and should be reconciled by the follow-up in (1).

3. **Docker security env var naming.** Local OpenSearch is started with the OpenSearch 2.x image vars
   `DISABLE_SECURITY_PLUGIN=true` + `DISABLE_INSTALL_DEMO_CONFIG=true` (per the `Makefile`), not the
   `plugins.security.disabled=true` form seen in some older instructions.
