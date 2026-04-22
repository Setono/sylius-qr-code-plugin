<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusQRCodePlugin\Factory\QRCodeFactory;
use Setono\SyliusQRCodePlugin\Factory\QRCodeFactoryInterface;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;

final class QRCodeFactoryTest extends TestCase
{
    private QRCodeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new QRCodeFactory(
            ProductRelatedQRCode::class,
            TargetUrlQRCode::class,
            defaultRedirectType: 302,
            defaultUtmSource: 'qr',
            defaultUtmMedium: 'qrcode',
        );
    }

    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $implemented = class_implements($this->factory);

        self::assertIsArray($implemented);
        self::assertContains(QRCodeFactoryInterface::class, $implemented);
    }

    /**
     * @test
     */
    public function it_creates_a_product_related_qr_code_of_the_configured_class(): void
    {
        $qrCode = $this->factory->createProductRelated();

        self::assertInstanceOf(ProductRelatedQRCode::class, $qrCode);
    }

    /**
     * @test
     */
    public function it_creates_a_target_url_qr_code_of_the_configured_class(): void
    {
        $qrCode = $this->factory->createTargetUrl();

        self::assertInstanceOf(TargetUrlQRCode::class, $qrCode);
    }

    /**
     * @test
     */
    public function it_seeds_product_qr_code_defaults_from_the_config(): void
    {
        $qrCode = $this->factory->createProductRelated();

        self::assertSame(302, $qrCode->getRedirectType());
        self::assertSame('qr', $qrCode->getUtmSource());
        self::assertSame('qrcode', $qrCode->getUtmMedium());
    }

    /**
     * @test
     */
    public function it_seeds_target_url_qr_code_defaults_from_the_config(): void
    {
        $qrCode = $this->factory->createTargetUrl();

        self::assertSame(302, $qrCode->getRedirectType());
        self::assertSame('qr', $qrCode->getUtmSource());
        self::assertSame('qrcode', $qrCode->getUtmMedium());
    }

    /**
     * @test
     */
    public function it_leaves_utm_campaign_unset_at_creation_time(): void
    {
        // utmCampaign is snapshotted from the slug at save time, not seeded by the factory.
        self::assertNull($this->factory->createProductRelated()->getUtmCampaign());
        self::assertNull($this->factory->createTargetUrl()->getUtmCampaign());
    }

    /**
     * @test
     */
    public function it_propagates_null_utm_defaults(): void
    {
        $factory = new QRCodeFactory(
            ProductRelatedQRCode::class,
            TargetUrlQRCode::class,
            defaultRedirectType: 307,
            defaultUtmSource: null,
            defaultUtmMedium: null,
        );

        $qrCode = $factory->createTargetUrl();

        self::assertNull($qrCode->getUtmSource());
        self::assertNull($qrCode->getUtmMedium());
        self::assertSame(307, $qrCode->getRedirectType());
    }
}
