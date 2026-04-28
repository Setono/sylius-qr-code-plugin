<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class QRCodeRepositoryTest extends KernelTestCase
{
    private QRCodeRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get('setono_sylius_qr_code.repository.qr_code');
        Assert::isInstanceOf($repository, QRCodeRepositoryInterface::class);
        $this->repository = $repository;

        $entityManager = $container->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @test
     */
    public function it_finds_a_qr_code_by_slug(): void
    {
        $this->persistQRCode('promo', enabled: true);

        $found = $this->repository->findOneBySlug('promo');

        self::assertNotNull($found);
        self::assertSame('promo', $found->getSlug());
    }

    /**
     * @test
     */
    public function it_returns_null_when_no_qr_code_matches_the_slug(): void
    {
        self::assertNull($this->repository->findOneBySlug('does-not-exist'));
    }

    /**
     * @test
     */
    public function it_finds_the_qr_code_by_slug_even_when_disabled(): void
    {
        $this->persistQRCode('archived', enabled: false);

        $found = $this->repository->findOneBySlug('archived');

        self::assertNotNull($found);
        self::assertFalse($found->isEnabled());
    }

    /**
     * @test
     */
    public function it_finds_only_enabled_qr_codes_when_filtering_for_enabled(): void
    {
        $this->persistQRCode('live', enabled: true);
        $this->persistQRCode('hidden', enabled: false);

        self::assertNotNull($this->repository->findOneEnabledBySlug('live'));
        self::assertNull($this->repository->findOneEnabledBySlug('hidden'));
    }

    /**
     * @test
     */
    public function it_returns_null_for_enabled_lookup_when_slug_is_unknown(): void
    {
        self::assertNull($this->repository->findOneEnabledBySlug('missing'));
    }

    /**
     * @test
     */
    public function it_counts_zero_scans_for_a_freshly_persisted_qr_code(): void
    {
        $qrCode = $this->persistQRCode('fresh', enabled: true);

        self::assertSame(0, $this->repository->getScansCount($qrCode));
    }

    /**
     * @test
     */
    public function it_counts_scans_for_a_qr_code(): void
    {
        $qrCode = $this->persistQRCode('scanned', enabled: true);

        $this->persistScan($qrCode);
        $this->persistScan($qrCode);
        $this->persistScan($qrCode);

        self::assertSame(3, $this->repository->getScansCount($qrCode));
    }

    /**
     * @test
     */
    public function it_only_counts_scans_belonging_to_the_given_qr_code(): void
    {
        $own = $this->persistQRCode('own', enabled: true);
        $other = $this->persistQRCode('other', enabled: true);

        $this->persistScan($own);
        $this->persistScan($other);
        $this->persistScan($other);

        self::assertSame(1, $this->repository->getScansCount($own));
        self::assertSame(2, $this->repository->getScansCount($other));
    }

    private function persistQRCode(string $slug, bool $enabled): QRCodeInterface
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setName($slug);
        $qrCode->setSlug($slug);
        $qrCode->setEnabled($enabled);
        $qrCode->setEmbedLogo(false);
        $qrCode->setRedirectType(307);
        $qrCode->setErrorCorrectionLevel(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM);
        $qrCode->setTargetUrl('https://example.com/' . $slug);

        $this->entityManager->persist($qrCode);
        $this->entityManager->flush();

        return $qrCode;
    }

    private function persistScan(QRCodeInterface $qrCode): void
    {
        $scan = new QRCodeScan();
        $scan->setQrCode($qrCode);
        $scan->setIpAddress('127.0.0.1');
        $scan->setUserAgent('PHPUnit');

        $this->entityManager->persist($scan);
        $this->entityManager->flush();
    }
}
