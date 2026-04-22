<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Product\Model\ProductInterface;

final class ProductRelatedQRCodeTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_has_no_product_by_default(): void
    {
        self::assertNull((new ProductRelatedQRCode())->getProduct());
    }

    /**
     * @test
     */
    public function it_exposes_the_product_via_accessor(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product);

        self::assertSame($product, $qrCode->getProduct());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_product(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product);
        $qrCode->setProduct(null);

        self::assertNull($qrCode->getProduct());
    }

    /**
     * @test
     */
    public function it_reports_its_type_as_product(): void
    {
        self::assertSame(QRCodeInterface::TYPE_PRODUCT, (new ProductRelatedQRCode())->getType());
    }
}
