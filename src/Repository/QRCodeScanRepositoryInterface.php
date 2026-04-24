<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Repository;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<QRCodeScanInterface>
 */
interface QRCodeScanRepositoryInterface extends RepositoryInterface
{
    public function countForQrCode(QRCodeInterface $qrCode): int;

    public function countForQrCodeSince(QRCodeInterface $qrCode, \DateTimeImmutable $since): int;

    /**
     * Returns counts per day (UTC) for the given QR code within [from, until]. Every day in
     * the window is present in the returned map — days with no scans return `0` — so a chart
     * consumer can render a contiguous line without post-processing.
     *
     * @return array<string, int> Map of `YYYY-MM-DD` → count
     */
    public function countDailyBuckets(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * @return list<QRCodeScanInterface>
     */
    public function findRecentForQrCode(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until, int $limit, int $offset = 0): array;
}
