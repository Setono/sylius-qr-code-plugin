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

        $result = [];
        foreach ($rows as $row) {
            $result[$row['bucket']] = (int) $row['count'];
        }

        return $result;
    }

    public function countWeeklyBuckets(QRCodeInterface $qrCode, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        /** @var list<array{scannedAt: \DateTimeImmutable}> $scans */
        $scans = $this->createQueryBuilder('s')
            ->select('s.scannedAt')
            ->andWhere('s.qrCode = :qrCode')
            ->andWhere('s.scannedAt >= :from')
            ->andWhere('s.scannedAt < :until')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()
            ->getArrayResult()
        ;

        $result = [];
        foreach ($scans as $scan) {
            $bucket = $scan['scannedAt']->format('o-\WW');
            $result[$bucket] = ($result[$bucket] ?? 0) + 1;
        }

        ksort($result);

        return $result;
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
