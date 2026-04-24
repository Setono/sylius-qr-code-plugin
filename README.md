# Setono SyliusQRCodePlugin

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

[Setono](https://setono.com) have made a bunch of [plugins for Sylius](https://github.com/Setono?q=plugin&sort=stargazers), and we have some guidelines
which we try to follow when developing plugins. These guidelines are used in this repository, and it gives you a very
solid base when developing plugins.

Enjoy! 

## Quickstart

1. Run
    ```shell
    composer create-project --prefer-source --no-install --remove-vcs setono/sylius-qr-code-plugin:1.14.x-dev ProjectName
    ``` 
    or just click the `Use this template` button at the right corner of this repository.
2. Run
   ```shell
   cd ProjectName && composer install
   ```
3. From the plugin skeleton root directory, run the following commands:

    ```bash
    php init
    (cd tests/Application && yarn install)
    (cd tests/Application && yarn build)
    (cd tests/Application && bin/console assets:install)
    
    (cd tests/Application && bin/console doctrine:database:create)
    (cd tests/Application && bin/console doctrine:schema:create)
   
    (cd tests/Application && bin/console sylius:fixtures:load -n)
    ```
   
4. Start your local PHP server: `symfony serve` (see https://symfony.com/doc/current/setup/symfony_server.html for docs)

To be able to set up a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

## Hooking into scan events

Every time the public `/qr/{slug}` endpoint resolves an enabled QR code, the plugin dispatches a
`Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent` before returning the `RedirectResponse`.
The event carries the resolved `QRCodeInterface` and the incoming `Request`, and is the single
extension point for anything you want to do on a scan — send it to your analytics pipeline,
Matomo, Google Analytics server-side, Segment, Slack, anything.

The plugin's own scan tracker is wired as a subscriber on this event, so you don't need to do
anything to keep the built-in `QRCodeScan` rows being persisted.

To add your own tracking, register a Symfony event subscriber or listener on
`QRCodeScannedEvent::class`:

```php
<?php

namespace App\EventSubscriber;

use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TrackScanInGoogleAnalytics implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            QRCodeScannedEvent::class => 'onScan',
        ];
    }

    public function onScan(QRCodeScannedEvent $event): void
    {
        $qrCode = $event->qrCode;
        $request = $event->request;

        // ... send a Measurement Protocol hit, enqueue a Messenger message, etc.
    }
}
```

Register it as a service with `autoconfigure: true` (Symfony picks up the tag automatically),
or add `tags: [kernel.event_subscriber]` explicitly.

**Listener exceptions never block the redirect.** `RedirectAction` wraps the dispatch call in a
`try/catch` — if a listener throws, the exception is logged at `error` level with the QR code
id and slug, and the user still gets redirected to the target URL. Misbehaving third-party
tracking code can never strand a scanner on a broken page.

If you need to **replace** the built-in tracker (e.g. async persistence via Symfony Messenger),
two options:

1. **Decorate the `ScanTrackerInterface` service.** The shipped subscriber delegates to it, so
   your replacement transparently picks up the built-in flow without you having to touch the
   event wiring.
2. **Remove the shipped subscriber tag and register your own subscriber.** Do this if you want
   to drop `QRCodeScan` persistence entirely or replace it with a very different storage shape.

[ico-version]: https://poser.pugx.org/setono/sylius-qr-code-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-qr-code-plugin/license
[ico-github-actions]: https://github.com/Setono/SyliusQRCodePlugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusQRCodePlugin/branch/1.12.x/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2FSyliusPluginSkeleton%2F1.12.x

[link-packagist]: https://packagist.org/packages/setono/sylius-qr-code-plugin
[link-github-actions]: https://github.com/Setono/SyliusQRCodePlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusQRCodePlugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/SyliusQRCodePlugin/1.12.x
