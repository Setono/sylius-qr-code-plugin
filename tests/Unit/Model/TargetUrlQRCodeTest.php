<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;

final class TargetUrlQRCodeTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_no_target_url_by_default(): void
    {
        self::assertNull((new TargetUrlQRCode())->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_exposes_the_target_url_via_accessor(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setTargetUrl('https://example.com/page');

        self::assertSame('https://example.com/page', $qrCode->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_target_url(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setTargetUrl('https://example.com/page');
        $qrCode->setTargetUrl(null);

        self::assertNull($qrCode->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_reports_its_type_as_target_url(): void
    {
        self::assertSame(QRCodeInterface::TYPE_TARGET_URL, (new TargetUrlQRCode())->getType());
    }
}
