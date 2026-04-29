<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Psr\Log\LoggerInterface;
use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolverInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Public endpoint at /qr/{slug}. Looks up an enabled QR code by slug, dispatches a scan event
 * for downstream listeners (including the plugin's own ScanTracker), and redirects to the
 * resolved target URL with UTM parameters appended.
 *
 * The HTTP status code for the redirect is plugin-wide configuration (`setono_sylius_qr_code.redirect_type`,
 * default `302`) rather than per-QR. This avoids the foot-gun where a permanent redirect (301) gets
 * cached aggressively by browsers and crawlers — once issued, you cannot repoint the slug without
 * users hitting the stale target. If your deployment has a different policy, override the parameter.
 *
 * Scan-driven side effects live behind the `QRCodeScannedEvent` dispatch — adopting apps can
 * add their own tracking by registering a listener on that event; the plugin ships a built-in
 * subscriber that persists a `QRCodeScan` row. Listener exceptions are caught and logged so a
 * misbehaving third-party listener cannot block the redirect.
 */
final class RedirectAction
{
    public function __construct(
        private readonly QRCodeRepositoryInterface $qrCodeRepository,
        private readonly TargetUrlResolverInterface $targetUrlResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly int $redirectType,
    ) {
    }

    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $qrCode = $this->qrCodeRepository->findOneEnabledBySlug($slug);

        if (null === $qrCode) {
            throw new NotFoundHttpException(sprintf('No enabled QR code with slug "%s".', $slug));
        }

        try {
            $targetUrl = $this->targetUrlResolver->resolve($qrCode);
        } catch (UnsupportedQRCodeException|\LogicException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        try {
            $this->eventDispatcher->dispatch(new QRCodeScannedEvent($qrCode, $request));
        } catch (\Throwable $exception) {
            // Scan-related side effects must never block the redirect — log and continue so a
            // misbehaving third-party listener can't lose a user's hit.
            $this->logger->error('Failed to dispatch QR code scan event.', [
                'exception' => $exception,
                'qr_code_id' => $qrCode->getId(),
                'slug' => $slug,
            ]);
        }

        return new RedirectResponse((string) $targetUrl, $this->redirectType);
    }
}
