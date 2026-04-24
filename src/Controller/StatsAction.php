<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeScanRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Admin page at /admin/qr-codes/{id}/stats. Renders totals, quick-stat cards for the last 7/30/90
 * days, a daily-bucket line chart, and a table of recent scans. All bucketing is UTC for v1.
 *
 * The range selector (switching the chart to 7/30/90 days via AJAX) and CSV export are tracked
 * separately in the tasks list — this action renders the initial page with a fixed 30-day default.
 */
final class StatsAction
{
    private const DEFAULT_RANGE_DAYS = 30;

    private const RECENT_SCANS_LIMIT = 50;

    public function __construct(
        private readonly QRCodeRepositoryInterface $qrCodeRepository,
        private readonly QRCodeScanRepositoryInterface $qrCodeScanRepository,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(int $id): Response
    {
        $qrCode = $this->qrCodeRepository->find($id);
        if (null === $qrCode) {
            throw new NotFoundHttpException(sprintf('No QR code with id %d.', $id));
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $range = $now->modify(sprintf('-%d days', self::DEFAULT_RANGE_DAYS));

        $totalScans = $this->qrCodeScanRepository->countForQrCode($qrCode);
        $scansLast7Days = $this->qrCodeScanRepository->countForQrCodeSince($qrCode, $now->modify('-7 days'));
        $scansLast30Days = $this->qrCodeScanRepository->countForQrCodeSince($qrCode, $now->modify('-30 days'));
        $scansLast90Days = $this->qrCodeScanRepository->countForQrCodeSince($qrCode, $now->modify('-90 days'));

        $dailyBuckets = $this->qrCodeScanRepository->countDailyBuckets($qrCode, $range, $now);

        $recentScans = $this->qrCodeScanRepository->findRecentForQrCode(
            $qrCode,
            $range,
            $now,
            self::RECENT_SCANS_LIMIT,
        );

        return new Response($this->twig->render('@SetonoSyliusQRCodePlugin/admin/qr_code/stats.html.twig', [
            'qr_code' => $qrCode,
            'total_scans' => $totalScans,
            'scans_last_7_days' => $scansLast7Days,
            'scans_last_30_days' => $scansLast30Days,
            'scans_last_90_days' => $scansLast90Days,
            'range_days' => self::DEFAULT_RANGE_DAYS,
            'daily_buckets' => $dailyBuckets,
            // Pre-split so the Twig template can hand the two lists straight to Chart.js without
            // needing a `|values` filter (Twig core ships `|keys` but not `|values`).
            'chart_labels' => array_keys($dailyBuckets),
            'chart_data' => array_values($dailyBuckets),
            'recent_scans' => $recentScans,
        ]));
    }
}
