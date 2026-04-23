<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tracker;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Symfony\Component\HttpFoundation\Request;

interface ScanTrackerInterface
{
    /**
     * Records a single scan of the given QR code. Populates scannedAt / ipAddress / userAgent
     * on a new `QRCodeScan` and persists it. The synchronous default implementation flushes
     * immediately; async implementations can be substituted without changing callers (see
     * design.md §3).
     */
    public function track(QRCodeInterface $qrCode, Request $request): void;
}
