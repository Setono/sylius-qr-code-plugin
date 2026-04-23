<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlQRCodeResolver;

final class TargetUrlQRCodeResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_the_stored_target_url(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setTargetUrl('https://example.com/page');

        self::assertSame(
            'https://example.com/page',
            (string) (new TargetUrlQRCodeResolver())->resolve($qrCode),
        );
    }

    /**
     * @test
     */
    public function it_rejects_non_target_url_qr_codes_with_unsupported_exception(): void
    {
        $this->expectException(UnsupportedQRCodeException::class);

        (new TargetUrlQRCodeResolver())->resolve(new ProductRelatedQRCode());
    }

    /**
     * @test
     */
    public function it_rejects_a_persisted_target_url_qr_code_with_no_target_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TargetUrlQRCodeResolver())->resolve(new TargetUrlQRCode());
    }
}
