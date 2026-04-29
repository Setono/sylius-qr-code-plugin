<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;

final class QRCodeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_initializes_with_sensible_defaults_and_no_identity(): void
    {
        $qrCode = new QRCode();

        self::assertNull($qrCode->getId());
        self::assertNull($qrCode->getName());
        self::assertNull($qrCode->getSlug());
        self::assertTrue($qrCode->isEnabled());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM, $qrCode->getErrorCorrectionLevel());
        self::assertNull($qrCode->getUtmSource());
        self::assertNull($qrCode->getUtmMedium());
        self::assertNull($qrCode->getUtmCampaign());
        self::assertNull($qrCode->getCreatedAt());
        self::assertNull($qrCode->getUpdatedAt());
    }

    /**
     * @test
     */
    public function it_initializes_scans_as_an_empty_collection(): void
    {
        $qrCode = new QRCode();

        self::assertCount(0, $qrCode->getScans());
    }

    /**
     * @test
     */
    public function it_exposes_the_name_via_accessor(): void
    {
        $qrCode = new QRCode();
        $qrCode->setName('Summer Sale');

        self::assertSame('Summer Sale', $qrCode->getName());

        $qrCode->setName(null);
        self::assertNull($qrCode->getName());
    }

    /**
     * @test
     */
    public function it_exposes_the_slug_via_accessor(): void
    {
        $qrCode = new QRCode();
        $qrCode->setSlug('summer-sale');

        self::assertSame('summer-sale', $qrCode->getSlug());
    }

    /**
     * @test
     */
    public function it_exposes_enabled_via_the_toggleable_trait(): void
    {
        $qrCode = new QRCode();
        self::assertTrue($qrCode->isEnabled());

        $qrCode->disable();
        self::assertFalse($qrCode->isEnabled());

        $qrCode->enable();
        self::assertTrue($qrCode->isEnabled());

        $qrCode->setEnabled(false);
        self::assertFalse($qrCode->isEnabled());
    }

    /**
     * @test
     */
    public function it_exposes_error_correction_level_via_accessor(): void
    {
        $qrCode = new QRCode();
        $qrCode->setErrorCorrectionLevel(QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH);

        self::assertSame('H', $qrCode->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_exposes_utm_parameters_via_accessors(): void
    {
        $qrCode = new QRCode();

        $qrCode->setUtmSource('qr');
        $qrCode->setUtmMedium('qrcode');
        $qrCode->setUtmCampaign('spring-2026');

        self::assertSame('qr', $qrCode->getUtmSource());
        self::assertSame('qrcode', $qrCode->getUtmMedium());
        self::assertSame('spring-2026', $qrCode->getUtmCampaign());

        $qrCode->setUtmSource(null);
        $qrCode->setUtmMedium(null);
        $qrCode->setUtmCampaign(null);

        self::assertNull($qrCode->getUtmSource());
        self::assertNull($qrCode->getUtmMedium());
        self::assertNull($qrCode->getUtmCampaign());
    }

    /**
     * @test
     */
    public function it_exposes_created_and_updated_timestamps_via_accessors(): void
    {
        $qrCode = new QRCode();
        $createdAt = new \DateTimeImmutable('2026-01-01T10:00:00Z');
        $updatedAt = new \DateTimeImmutable('2026-02-01T10:00:00Z');

        $qrCode->setCreatedAt($createdAt);
        $qrCode->setUpdatedAt($updatedAt);

        self::assertSame($createdAt, $qrCode->getCreatedAt());
        self::assertSame($updatedAt, $qrCode->getUpdatedAt());
    }

    /**
     * @test
     */
    public function it_returns_the_same_collection_instance_across_calls_to_get_scans(): void
    {
        $qrCode = new QRCode();

        self::assertSame($qrCode->getScans(), $qrCode->getScans());
    }

    /**
     * @test
     */
    public function it_reports_zero_scans_for_a_new_qr_code(): void
    {
        self::assertSame(0, (new QRCode())->getScansCount());
    }

    /**
     * @test
     */
    public function it_throws_when_get_type_is_called_on_the_base_class(): void
    {
        $this->expectException(\LogicException::class);

        (new QRCode())->getType();
    }
}
