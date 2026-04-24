<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Repository;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * @method QRCodeScanInterface|null find($id, $lockMode = null, $lockVersion = null)
 */
class QRCodeScanRepository extends EntityRepository implements QRCodeScanRepositoryInterface
{
    public function countForQrCode(QRCodeInterface $qrCode): int
    {
        /** @var int|string $count */
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.qrCode = :qrCode')
            ->setParameter('qrCode', $qrCode)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }

    public function countForQrCodeSince(QRCodeInterface $qrCode, \DateTimeImmutable $since): int
    {
        /** @var int|string $count */
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.qrCode = :qrCode')
            ->andWhere('s.scannedAt >= :since')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }

    public function countDailyBuckets(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        /** @var list<array{bucket: string, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select("SUBSTRING(s.scannedAt, 1, 10) AS bucket, COUNT(s.id) AS count")
            ->andWhere('s.qrCode = :qrCode')
            ->andWhere('s.scannedAt >= :from')
            ->andWhere('s.scannedAt < :until')
            ->groupBy('bucket')
            ->orderBy('bucket', 'ASC')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()
            ->getArrayResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['bucket']] = (int) $row['count'];
        }

        // Zero-fill every day in the window so callers (the stats chart in particular) get a
        // contiguous series without having to post-process — a line chart with gaps draws as a
        // broken line, which is misleading when the gap just means "no scans that day".
        $filled = [];
        $cursor = $from->setTime(0, 0);
        $end = $until->setTime(0, 0);
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $filled[$key] = $counts[$key] ?? 0;
            $cursor = $cursor->modify('+1 day');
        }

        return $filled;
    }

    public function findRecentForQrCode(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until, int $limit, int $offset = 0): array
    {
        /** @var list<QRCodeScanInterface> $scans */
        $scans = $this->createQueryBuilder('s')
            ->andWhere('s.qrCode = :qrCode')
            ->andWhere('s.scannedAt >= :from')
            ->andWhere('s.scannedAt < :until')
            ->orderBy('s.scannedAt', 'DESC')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;

        return $scans;
    }
}
