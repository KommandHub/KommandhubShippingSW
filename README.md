# KommandhubShipping for Shopware 6

Real-time delivery rates and multi-carrier fulfilment for African couriers in Shopware 6.

[![Shopware](https://img.shields.io/badge/Shopware-~6.6.0%20%7C%7C%20~6.7.0-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache-2.0-blue)](LICENSE)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Features](#features)
- [Local development](#local-development)
- [Makefile commands](#makefile-commands)
- [Testing](#testing)
- [Code quality](#code-quality)
- [CI/CD](#cicd)
- [Release process](#release-process)
- [Logging and debugging](#logging-and-debugging)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | |
| --- | --- |
| Shopware | `~6.6.0 || ~6.7.0` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |

## Installation

### Via Composer (recommended)

```bash
composer require kommandhub/shipping-sw
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubShippingSW
bin/console cache:clear
```

### Manual upload

Download the release zip and install it under **Extensions > My extensions >
Upload extension**, then activate it.

## Configuration

**Settings > Extensions > KommandhubShipping for Shopware 6**

Every setting is sales-channel scoped: use the sales-channel selector at the top
of the configuration page to override a value for one channel only.

## Architecture

Feature-first modules under `src/`, following Shopware's own plugin layout. A
top-level directory *is* a boundary; inside it, flat Symfony-idiomatic folders
(`Service`, `Subscriber`, `Handler`, `Struct`, `Event`, `Enum`, `Controller`).
No `Application/Domain/Infrastructure` nesting — one obvious home per class.

Always present:

| Path | Responsibility |
| --- | --- |
| `src/KommandhubShippingSW.php` | plugin bootstrap and lifecycle hooks |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `src/Exception/` | plugin-scoped exception base |
| `src/Resources/config/` | `services.yml`, `config.xml`, `packages/` |
| `tests/Unit`, `tests/Integration` | mirror `src/` |

## Features

### Administration

Vue/JS sources live in `src/Resources/app/administration/src/`. Built output in
`src/Resources/public/` is **generated** — never hand-edit it, and rebuild with
`make zip` (or `bin/build-administration.sh` in the container) after changing a
source file.

- `main.js` — entry point: registers locales, ACL, modules and services.
- `acl/index.js` — the plugin's admin permissions.
- `snippet/*.json` — one file per locale, mirroring the same key tree.

Any privilege enforced here **must** also be enforced server-side via `_acl` on
the route.

### Shipping API client

`src/Client/` is the only place that talks HTTP.

| Layer | Responsibility |
| --- | --- |
| `Http/ShippingHttpClient` | base URL, credentials, transport errors |
| `Resource/*` | one class per endpoint group, one method per documented call |
| `ShippingClient` | facade services depend on |

Add a new call by adding a method to the matching `Resource/` class — never by
issuing HTTP from a service.

### Custom fields

`kommandhub_shipping_fieldset` is installed on the order transaction entity and carries
`kommandhub_shipping_reference`.

Keys are global across the installation and are the lookup key for stored data —
they live in `Util/ShippingConstants` and nowhere else. The installer is
idempotent (it runs on both install and update); uninstall only removes the set
when the merchant did not choose to keep user data.

### Scheduled tasks

`kommandhub_shipping.sync` runs every hour via Shopware's scheduled-task queue. It only
runs when a worker is running (`bin/console messenger:consume`) — the admin
"Scheduled tasks" screen shows the next due time.

Handlers run detached from any request: resolve the sales channel per record,
and never let an exception escape (a throw retries the message and can wedge
the queue).

### Storefront

- `src/Resources/app/storefront/src/` — JS plugins, registered in `main.js`.
- `src/Resources/views/storefront/` — Twig overrides. **Namespace every block**
  you add (`{% block kommandhub_shipping_foo %}`) so it cannot collide with another plugin
  extending the same template.
- `src/Resources/snippet/<locale>/` — storefront translations; every locale file
  must carry the same key tree.

Built output under `src/Resources/app/storefront/dist/` is generated.

### Webhooks

`POST /shipping/webhook` — public, unauthenticated, HMAC-verified.

1. `WebhookSignatureValidator` rejects anything inauthentic (403). This is the
   security boundary; everything downstream trusts the payload.
2. `WebhookEventFactory` maps the provider's event string to a typed event.
   Unknown types are logged and answered **200** so the provider stops retrying.
3. The event is dispatched; subscribers under `Webhook/Subscriber/` do the work.

Register the URL in the provider's dashboard per sales channel domain.


## Local development

The plugin is developed inside a Docker stack that runs a full Shopware install
with this directory mounted at `custom/static-plugins/KommandhubShippingSW`.

```bash
git clone https://github.com/Kommandhub/KommandhubShippingSW.git
cd KommandhubShippingSW

make up     # build the image, start Shopware, install dependencies
make shell  # bash into the container

# inside the container
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubShippingSW
```

Storefront: <http://localhost> · Administration: <http://localhost/admin>
(`admin` / `shopware`).

## Makefile commands

| Command | What it does |
| --- | --- |
| `make up` / `make down` | start / tear down the stack |
| `make restart` | `down` then `up` |
| `make shell` | shell into the container |
| `make test` | PHPUnit; filter with `make test FILTER=SomeTest` |
| `make test-coverage` | coverage text report |
| `make analyse` | PHPStan on `src/` |
| `make cs` / `make cs-fix` | php-cs-fixer dry-run / apply |
| `make validate-plugin` | shopware-cli store-compliance check |
| `make changelog` | render `CHANGELOG.md` as the Store will |
| `make zip` | build a distributable zip into `build/` |
| `make cli ARGS="..."` | any other shopware-cli command |

## Testing

```bash
make test
make test FILTER=ConfigTest
make test-coverage
```

- `tests/Unit/` mirrors `src/`. No kernel, no database — fast, and what CI runs.
- `tests/Integration/` needs a booted Shopware kernel. Mark those tests
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
- Add or update a test with every behaviour change.

## Code quality

```bash
make cs-fix && make analyse && make test
```

All three must pass before a commit. PHPStan runs at the level pinned in
`phpstan.dist.neon`; php-cs-fixer enforces PSR-12 plus the rules in
`.php-cs-fixer.dist.php`.

## CI/CD

`.github/workflows/php.yml` runs on every push to `main`/`develop` and on every
pull request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Land everything on `develop`; make sure the local gate passes.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` (and the localised
   variants). Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Merge `develop` into `main` and tag the release.

## Logging and debugging

Enable **debug logging** in the plugin configuration; entries land in
`var/log/kommandhub_shipping_<env>.log` (rotating, 7 files).

`error` and above are **always** written regardless of the toggle, so production
keeps a trail of failures. Both the toggle and the level filter are
sales-channel scoped — pass the sales channel id in the log context so it
resolves against the right scope:

```php
$this->logger->info('something happened', [
    ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
]);
```

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Never open a
public issue for one, and never paste real credentials into an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `develop`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
