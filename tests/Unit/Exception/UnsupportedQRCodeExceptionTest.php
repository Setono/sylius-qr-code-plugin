<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;

final class UnsupportedQRCodeExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_includes_the_concrete_qr_code_class_in_the_message(): void
    {
        $exception = new UnsupportedQRCodeException(new TargetUrlQRCode());

        self::assertStringContainsString(TargetUrlQRCode::class, $exception->getMessage());
    }

    /**
     * @test
     */
    public function it_preserves_the_previous_exception(): void
    {
        $previous = new \RuntimeException('inner');

        $exception = new UnsupportedQRCodeException(new TargetUrlQRCode(), $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @test
     */
    public function it_defaults_previous_to_null(): void
    {
        $exception = new UnsupportedQRCodeException(new TargetUrlQRCode());

        self::assertNull($exception->getPrevious());
    }
}
