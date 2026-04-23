<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Psr\Log\LoggerInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolverInterface;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Tracker\ScanTrackerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public endpoint at /qr/{slug}. Looks up an enabled QR code by slug, records a scan, and
 * redirects to the resolved target URL with UTM parameters appended.
 *
 * Availability checks (product enabled, enabled-on-channel, translation slug present, …) live
 * in the subtype resolvers. The action's job is purely to find the QR, ask the resolver for
 * a URL, and if the resolver can't produce one (LogicException) turn that into a 404.
 */
final class RedirectAction
{
    public function __construct(
        private readonly QRCodeRepositoryInterface $qrCodeRepository,
        private readonly TargetUrlResolverInterface $targetUrlResolver,
        private readonly ScanTrackerInterface $scanTracker,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
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
            $this->scanTracker->track($qrCode, $request);
        } catch (\Throwable $exception) {
            // Tracking must never block the redirect — log and continue (see spec scenario
            // "Tracker failure does not block redirect").
            $this->logger->error('Failed to track QR code scan.', [
                'exception' => $exception,
                'qr_code_id' => $qrCode->getId(),
                'slug' => $slug,
            ]);
        }

        return new RedirectResponse((string) $targetUrl, $qrCode->getRedirectType());
    }
}
