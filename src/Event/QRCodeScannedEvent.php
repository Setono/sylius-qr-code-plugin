<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Event;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Dispatched by `Setono\SyliusQRCodePlugin\Controller\RedirectAction` every time an enabled QR
 * code is resolved, before the RedirectResponse is returned to the scanner.
 *
 * The plugin's built-in scan tracker (ScanTracker) subscribes to this event to persist a
 * `QRCodeScan` row. Adopting applications can register their own listeners/subscribers on the
 * same event to add extra tracking — analytics pipelines, Matomo, Google Analytics server-side,
 * Segment, Slack notifications, etc. — without forking or decorating the redirect action.
 *
 * Listener exceptions do NOT block the redirect: the dispatch call is wrapped in a try/catch
 * in RedirectAction, so a misbehaving third-party listener still lets the user reach the
 * target URL.
 *
 * Plain value object — no `extends Symfony\Contracts\EventDispatcher\Event`. Symfony's PSR-14
 * dispatcher accepts any object, and staying inheritance-free keeps the plugin framework-light
 * for apps that wire their own dispatcher.
 */
final class QRCodeScannedEvent
{
    public function __construct(
        public readonly QRCodeInterface $qrCode,
        public readonly Request $request,
    ) {
    }
}
