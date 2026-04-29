# Setono SyliusQRCodePlugin

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

Generate, manage, and track QR codes from Sylius admin. Each QR code points at a stable
plugin-owned redirect URL (`/qr/{slug}`) so the printed code never has to change when the
underlying destination does, and every scan is recorded against the QR code that produced it.

## Features

- **Two QR code types** out of the box:
    - **URL QR code** — redirects to any absolute target URL.
    - **Product QR code** — redirects to a Sylius product's slug-based URL on the current channel.
    Both are first-class Sylius resources backed by Single Table Inheritance, so you can add
    your own subtype later without touching the existing schema.
- **Per-channel rendering.** The same QR code rendered against two channels encodes two
  different redirect URLs (the channel hostname is baked into the image), so you can print
  channel-specific codes from a single QR code definition.
- **Stable public redirect** at `GET /qr/{slug}` with a plugin-wide HTTP status code
  (`setono_sylius_qr_code.redirect_type`; default `302`). UTM parameters configured on
  the QR code are appended to the resolved target URL.
- **PNG, SVG, and PDF download** from the admin (`/admin/qr-codes/{id}/download/{format}/{channel}`).
- **Scan tracking.** Every successful redirect persists a `QRCodeScan` row (timestamp,
  IP, user agent) and dispatches a `QRCodeScannedEvent` for your own analytics integrations.
  Listener exceptions never block the redirect.
- **Admin stats page** per QR code — total / 7d / 30d / 90d, scans-over-time, recent scans.
- **Bulk-generate product QR codes** from the Sylius product grid: select products, click the
  bulk action, get one Product QR code per selected product (skipping ones that already have
  a QR code with that slug).
- **10 translations** shipped: en, da, de, es, fr, it, nl, no, pl, sv.

## Requirements

- PHP 8.1+
- Sylius 1.x (the plugin is developed against the 1.14 branch; check the branch alias in
  `composer.json` for the current target)
- A relational database supported by Doctrine ORM (MySQL/MariaDB, PostgreSQL, SQLite)
- `stof/doctrine-extensions-bundle` enabled with the timestampable listener turned on
  (the plugin uses Gedmo's `@Timestampable` for `created_at` / `updated_at` and `scanned_at`)

## Installation

### 1. Require the plugin

```shell
composer require setono/sylius-qr-code-plugin
```

### 2. Register the bundle

The plugin **must** be registered *above* `SyliusGridBundle` in `config/bundles.php`. The
plugin's grid configuration references container parameters (e.g.
`setono_sylius_qr_code.model.qr_code.class`) that are registered by the plugin's
`sylius_resource` resources. If `SyliusGridBundle` boots first, it tries to resolve those
parameters before the plugin has had a chance to register them and you get:

```
You have requested a non-existent parameter "setono_sylius_qr_code.model.qr_code.class".
```

```php
// config/bundles.php
return [
    // ...
    Setono\SyliusQRCodePlugin\SetonoSyliusQRCodePlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    // ...
];
```

### 3. Import routes

```yaml
# config/routes/setono_sylius_qr_code.yaml
setono_sylius_qr_code:
    resource: "@SetonoSyliusQRCodePlugin/Resources/config/routes.yaml"
```

If your store doesn't use locale-prefixed URLs, import `routes_no_locale.yaml` instead. The
two files are parallel dispatchers — pick the one that matches how the rest of your shop is
wired.

The import registers:
- `GET /qr/{slug}` — public redirect (shop)
- `/admin/qr-codes/...` — admin grid, create/update/show/delete, stats, download, bulk-generate

### 4. Verify Gedmo's timestampable listener is enabled

A standard Sylius installation already ships `stof/doctrine-extensions-bundle` with the
`timestampable` listener enabled — there is nothing to do on a stock Sylius project. If
you are running a customised setup that disables it, re-enable it for the default ORM
manager:

```yaml
# config/packages/stof_doctrine_extensions.yaml
stof_doctrine_extensions:
    orm:
        default:
            timestampable: true
```

### 5. Update the database schema

Either generate a migration:

```shell
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

…or, in a fresh project, just create the schema:

```shell
bin/console doctrine:schema:update --force
```

The plugin creates two tables: `setono_sylius_qr_code__qr_code` (single-table inheritance for
both subtypes) and `setono_sylius_qr_code__qr_code_scan`.

That's it — visit `/admin/qr-codes/` to see the grid.

## Public redirect endpoint

`GET /qr/{slug}` is the URL the QR codes encode. The handler:

1. Looks up an enabled QR code by slug. Unknown / disabled slugs return 404.
2. Resolves the target URL via `TargetUrlResolverInterface` (subtype-aware: product → channel
   product slug URL, target URL → the stored URL, decorated with UTM parameters).
3. Persists a `QRCodeScan` and dispatches `QRCodeScannedEvent`.
4. Returns a redirect with the plugin-wide configured status (`setono_sylius_qr_code.redirect_type`,
   default `302`). The status code is plugin-wide rather than per-QR because permanent
   redirects (301) get cached aggressively by browsers and crawlers — once issued, the slug
   cannot be repointed without users hitting the stale target. Override the parameter if your
   deployment has a different policy.

Slugs are restricted to `[a-z0-9-]+`. The factory derives slugs Sylius-style from the entity
name, so you usually don't pick them by hand.

## Configuration reference

Defaults match the snippet below — drop only the keys you want to change.

```yaml
# config/packages/setono_sylius_qr_code.yaml
setono_sylius_qr_code:
    # HTTP status code returned by the public /qr/{slug} redirect. Plugin-wide, not per-QR —
    # 302 (the default) is the safe choice, because 301 gets cached by browsers and crawlers
    # and prevents repointing the slug. Allowed: 301, 302, 307.
    redirect_type: 302

    utm:
        # Default UTM source/medium written onto new QR codes by the factory. Each QR code
        # then carries its own snapshot — changing these defaults later does NOT rewrite
        # existing QR codes.
        source: qr
        medium: qrcode

    # Each resource block accepts the standard Sylius `classes.{model,controller,repository,form,factory}`
    # overrides if you need to subclass the entity, swap the controller, etc.
    resources:
        qr_code: ~
        product_related_qr_code: ~
        target_url_qr_code: ~
        qr_code_scan: ~
```

## Customization

### Hooking into scan events

Every time the public `/qr/{slug}` endpoint resolves an enabled QR code, the plugin dispatches
`Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent` before returning the redirect. The event
carries the resolved `QRCodeInterface` and the incoming `Request` and is the single extension
point for anything you want to do on a scan — analytics, Slack notifications, server-side
Google Analytics / Matomo / Segment, queueing async work, anything.

The plugin's own scan tracker is wired as a subscriber on this event, so you don't need to
do anything to keep the built-in `QRCodeScan` rows being persisted.

```php
namespace App\EventSubscriber;

use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TrackScanInGoogleAnalytics implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [QRCodeScannedEvent::class => 'onScan'];
    }

    public function onScan(QRCodeScannedEvent $event): void
    {
        $qrCode = $event->qrCode;
        $request = $event->request;

        // … send a Measurement Protocol hit, enqueue a Messenger message, etc.
    }
}
```

Register it with `autoconfigure: true` (Symfony picks up the tag automatically) or with
`tags: [kernel.event_subscriber]` explicitly.

**Listener exceptions never block the redirect.** `RedirectAction` wraps the dispatch in a
try/catch — if a listener throws, the exception is logged at `error` level with the QR code
id and slug, and the user is still redirected. Misbehaving third-party tracking code can
never strand a scanner on a broken page.

### Replacing the built-in scan tracker

Two options, depending on intent:

1. **Decorate `Setono\SyliusQRCodePlugin\Tracker\ScanTrackerInterface`.** The shipped
   subscriber delegates to it, so your replacement transparently picks up the built-in flow
   without touching the event wiring. Useful for swapping in async persistence (Symfony
   Messenger, write-behind cache, etc.).
2. **Remove the shipped subscriber and register your own.** Use this when you want to drop
   `QRCodeScan` persistence entirely or replace it with a fundamentally different storage
   shape.

### Writing a custom target URL resolver

`TargetUrlResolverInterface` is the seam for adding new QR code subtypes (or rerouting an
existing one). Each implementation reports `supports(QRCodeInterface)` and `resolve(...)`;
the composite resolver picks the first supporting service. Tag your service with
`setono_sylius_qr_code.target_url_resolver`:

```xml
<service id="App\Resolver\MyCustomResolver">
    <tag name="setono_sylius_qr_code.target_url_resolver" priority="100"/>
</service>
```

UTM parameters are layered on top by `UtmTargetUrlResolver`, which decorates the composite —
you do not need to handle UTM in your subtype resolver.

### Choosing a default channel

When an admin downloads a QR code without picking a channel (the channel field defaults to
"first enabled"), the plugin asks `Setono\SyliusQRCodePlugin\Channel\DefaultChannelResolverInterface`.
The shipped `FirstEnabledChannelResolver` returns the first channel the channel repository
considers enabled. Bind a different implementation to the interface to change that policy:

```xml
<service id="Setono\SyliusQRCodePlugin\Channel\DefaultChannelResolverInterface"
         alias="App\Channel\MyChannelResolver"/>
```

### Subclassing the QR code entities

The plugin ships `<mapped-superclass>` Doctrine mappings, so you can override any model class
the standard Sylius way:

```yaml
# config/packages/setono_sylius_qr_code.yaml
setono_sylius_qr_code:
    resources:
        product_related_qr_code:
            classes:
                model: App\Entity\QRCode\ProductRelatedQRCode
```

The plugin's `QRCodeDiscriminatorMapListener` rebuilds the Single Table Inheritance
discriminator map from the resource configuration at runtime, so your subclass is wired
automatically — no need to duplicate STI annotations on the base class. App-level subclasses
can also extend the STI hierarchy with their own `DiscriminatorMap` if you need to add new
subtypes that aren't first-class plugin resources.

[ico-version]: https://poser.pugx.org/setono/sylius-qr-code-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-qr-code-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-qr-code-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-qr-code-plugin/branch/master/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2Fsylius-qr-code-plugin%2Fmaster

[link-packagist]: https://packagist.org/packages/setono/sylius-qr-code-plugin
[link-github-actions]: https://github.com/Setono/sylius-qr-code-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-qr-code-plugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/sylius-qr-code-plugin/master
