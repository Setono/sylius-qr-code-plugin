<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Repository\QRCodeScanRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class QRCodeScanRepositoryTest extends KernelTestCase
{
    private QRCodeScanRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get('setono_sylius_qr_code.repository.qr_code_scan');
        Assert::isInstanceOf($repository, QRCodeScanRepositoryInterface::class);
        $this->repository = $repository;

        $entityManager = $container->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @test
     */
    public function it_counts_scans_for_a_qr_code(): void
    {
        $qrCode = $this->persistQRCode('count');

        $this->persistScan($qrCode, '2026-04-20 10:00:00');
        $this->persistScan($qrCode, '2026-04-21 11:00:00');
        $this->persistScan($qrCode, '2026-04-22 12:00:00');

        self::assertSame(3, $this->repository->countForQrCode($qrCode));
    }

    /**
     * @test
     */
    public function it_only_counts_scans_belonging_to_the_given_qr_code(): void
    {
        $own = $this->persistQRCode('own');
        $other = $this->persistQRCode('other');

        $this->persistScan($own, '2026-04-20 10:00:00');
        $this->persistScan($other, '2026-04-20 10:00:00');
        $this->persistScan($other, '2026-04-21 10:00:00');

        self::assertSame(1, $this->repository->countForQrCode($own));
        self::assertSame(2, $this->repository->countForQrCode($other));
    }

    /**
     * @test
     */
    public function it_counts_only_scans_at_or_after_the_since_date(): void
    {
        $qrCode = $this->persistQRCode('since');

        $this->persistScan($qrCode, '2026-04-19 23:59:59');
        $this->persistScan($qrCode, '2026-04-20 00:00:00');
        $this->persistScan($qrCode, '2026-04-21 12:00:00');

        $since = new \DateTimeImmutable('2026-04-20 00:00:00');

        self::assertSame(2, $this->repository->countForQrCodeSince($qrCode, $since));
    }

    /**
     * @test
     */
    public function it_returns_zero_filled_daily_buckets_for_the_full_window(): void
    {
        $qrCode = $this->persistQRCode('daily');

        $this->persistScan($qrCode, '2026-04-20 10:00:00');
        $this->persistScan($qrCode, '2026-04-20 18:00:00');
        $this->persistScan($qrCode, '2026-04-22 09:00:00');

        $from = new \DateTimeImmutable('2026-04-20 00:00:00');
        $until = new \DateTimeImmutable('2026-04-23 00:00:00');

        $buckets = $this->repository->countDailyBuckets($qrCode, $from, $until);

        self::assertSame([
            '2026-04-20' => 2,
            '2026-04-21' => 0,
            '2026-04-22' => 1,
            '2026-04-23' => 0,
        ], $buckets);
    }

    /**
     * @test
     */
    public function it_excludes_scans_outside_the_window_from_daily_buckets(): void
    {
        $qrCode = $this->persistQRCode('outside');

        $this->persistScan($qrCode, '2026-04-19 23:00:00');
        $this->persistScan($qrCode, '2026-04-23 00:00:01');

        $from = new \DateTimeImmutable('2026-04-20 00:00:00');
        $until = new \DateTimeImmutable('2026-04-23 00:00:00');

        $buckets = $this->repository->countDailyBuckets($qrCode, $from, $until);

        self::assertSame(0, array_sum($buckets));
        self::assertSame([
            '2026-04-20' => 0,
            '2026-04-21' => 0,
            '2026-04-22' => 0,
            '2026-04-23' => 0,
        ], $buckets);
    }

    /**
     * @test
     */
    public function it_returns_recent_scans_in_descending_order_within_the_window(): void
    {
        $qrCode = $this->persistQRCode('recent');

        $this->persistScan($qrCode, '2026-04-19 23:59:59');
        $oldest = $this->persistScan($qrCode, '2026-04-20 09:00:00');
        $middle = $this->persistScan($qrCode, '2026-04-21 09:00:00');
        $newest = $this->persistScan($qrCode, '2026-04-22 09:00:00');
        $this->persistScan($qrCode, '2026-04-23 00:00:01');

        $from = new \DateTimeImmutable('2026-04-20 00:00:00');
        $until = new \DateTimeImmutable('2026-04-23 00:00:00');

        $scans = $this->repository->findRecentForQrCode($qrCode, $from, $until, 10);

        self::assertCount(3, $scans);
        self::assertSame($newest->getId(), $scans[0]->getId());
        self::assertSame($middle->getId(), $scans[1]->getId());
        self::assertSame($oldest->getId(), $scans[2]->getId());
    }

    /**
     * @test
     */
    public function it_paginates_recent_scans_via_limit_and_offset(): void
    {
        $qrCode = $this->persistQRCode('paged');

        $first = $this->persistScan($qrCode, '2026-04-20 10:00:00');
        $second = $this->persistScan($qrCode, '2026-04-21 10:00:00');
        $third = $this->persistScan($qrCode, '2026-04-22 10:00:00');

        $from = new \DateTimeImmutable('2026-04-20 00:00:00');
        $until = new \DateTimeImmutable('2026-04-23 00:00:00');

        $page1 = $this->repository->findRecentForQrCode($qrCode, $from, $until, 2);
        $page2 = $this->repository->findRecentForQrCode($qrCode, $from, $until, 2, 2);

        self::assertCount(2, $page1);
        self::assertCount(1, $page2);

        self::assertSame($third->getId(), $page1[0]->getId());
        self::assertSame($second->getId(), $page1[1]->getId());
        self::assertSame($first->getId(), $page2[0]->getId());
    }

    /**
     * @test
     */
    public function it_only_returns_scans_belonging_to_the_given_qr_code(): void
    {
        $own = $this->persistQRCode('own');
        $other = $this->persistQRCode('other');

        $ownScan = $this->persistScan($own, '2026-04-20 10:00:00');
        $this->persistScan($other, '2026-04-20 10:00:00');

        $from = new \DateTimeImmutable('2026-04-20 00:00:00');
        $until = new \DateTimeImmutable('2026-04-23 00:00:00');

        $scans = $this->repository->findRecentForQrCode($own, $from, $until, 10);

        self::assertCount(1, $scans);
        self::assertSame($ownScan->getId(), $scans[0]->getId());
    }

    private function persistQRCode(string $slug): QRCodeInterface
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setName($slug);
        $qrCode->setSlug($slug);
        $qrCode->setEnabled(true);
        $qrCode->setEmbedLogo(false);
        $qrCode->setRedirectType(307);
        $qrCode->setErrorCorrectionLevel(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM);
        $qrCode->setTargetUrl('https://example.com/' . $slug);

        $this->entityManager->persist($qrCode);
        $this->entityManager->flush();

        return $qrCode;
    }

    private function persistScan(QRCodeInterface $qrCode, string $scannedAt): QRCodeScan
    {
        $scan = new QRCodeScan();
        $scan->setQrCode($qrCode);
        $scan->setIpAddress('127.0.0.1');
        $scan->setUserAgent('PHPUnit');
        // Force the scannedAt timestamp explicitly so tests can assert window boundaries
        // deterministically — Gedmo's create-listener would otherwise stamp `now`.
        $scan->setScannedAt(new \DateTimeImmutable($scannedAt));

        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        return $scan;
    }
}
