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
     * Returns counts per day (UTC) for the given QR code within [from, until].
     *
     * @return array<string, int> Map of `YYYY-MM-DD` → count
     */
    public function countDailyBuckets(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * Returns counts per ISO week (UTC) for the given QR code within [from, until].
     *
     * @return array<string, int> Map of `YYYY-Www` → count
     */
    public function countWeeklyBuckets(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * @return list<QRCodeScanInterface>
     */
    public function findRecentForQrCode(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until, int $limit, int $offset = 0): array;
}
