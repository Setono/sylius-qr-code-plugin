<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;

final class QRCodeScanTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_initializes_with_no_values(): void
    {
        $scan = new QRCodeScan();

        self::assertNull($scan->getId());
        self::assertNull($scan->getQrCode());
        self::assertNull($scan->getScannedAt());
        self::assertNull($scan->getIpAddress());
        self::assertNull($scan->getUserAgent());
    }

    /**
     * @test
     */
    public function it_exposes_the_related_qr_code_via_accessor(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();

        $scan = new QRCodeScan();
        $scan->setQrCode($qrCode);

        self::assertSame($qrCode, $scan->getQrCode());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_related_qr_code(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();

        $scan = new QRCodeScan();
        $scan->setQrCode($qrCode);
        $scan->setQrCode(null);

        self::assertNull($scan->getQrCode());
    }

    /**
     * @test
     */
    public function it_exposes_the_scanned_at_timestamp_via_accessor(): void
    {
        $scannedAt = new \DateTimeImmutable('2026-04-22T12:00:00Z');

        $scan = new QRCodeScan();
        $scan->setScannedAt($scannedAt);

        self::assertSame($scannedAt, $scan->getScannedAt());
    }

    /**
     * @test
     */
    public function it_exposes_the_ip_address_via_accessor(): void
    {
        $scan = new QRCodeScan();
        $scan->setIpAddress('203.0.113.5');

        self::assertSame('203.0.113.5', $scan->getIpAddress());
    }

    /**
     * @test
     */
    public function it_accepts_an_ipv6_address_at_the_45_character_boundary(): void
    {
        $ipv6 = '::ffff:255.255.255.255';

        $scan = new QRCodeScan();
        $scan->setIpAddress($ipv6);

        self::assertSame($ipv6, $scan->getIpAddress());
    }

    /**
     * @test
     */
    public function it_stores_a_short_user_agent_verbatim(): void
    {
        $userAgent = 'Mozilla/5.0 (iPhone)';

        $scan = new QRCodeScan();
        $scan->setUserAgent($userAgent);

        self::assertSame($userAgent, $scan->getUserAgent());
    }

    /**
     * @test
     */
    public function it_stores_an_empty_user_agent_as_empty_string(): void
    {
        $scan = new QRCodeScan();
        $scan->setUserAgent('');

        self::assertSame('', $scan->getUserAgent());
    }

    /**
     * @test
     */
    public function it_truncates_a_user_agent_longer_than_the_max_length(): void
    {
        $overlong = str_repeat('a', QRCodeScan::USER_AGENT_MAX_LENGTH + 100);

        $scan = new QRCodeScan();
        $scan->setUserAgent($overlong);

        $stored = $scan->getUserAgent();

        self::assertNotNull($stored);
        self::assertSame(QRCodeScan::USER_AGENT_MAX_LENGTH, mb_strlen($stored));
        self::assertSame(str_repeat('a', QRCodeScan::USER_AGENT_MAX_LENGTH), $stored);
    }

    /**
     * @test
     */
    public function it_accepts_a_user_agent_at_exactly_the_max_length(): void
    {
        $atBoundary = str_repeat('b', QRCodeScan::USER_AGENT_MAX_LENGTH);

        $scan = new QRCodeScan();
        $scan->setUserAgent($atBoundary);

        self::assertSame($atBoundary, $scan->getUserAgent());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_user_agent(): void
    {
        $scan = new QRCodeScan();
        $scan->setUserAgent('Mozilla/5.0');
        $scan->setUserAgent(null);

        self::assertNull($scan->getUserAgent());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_ip_address(): void
    {
        $scan = new QRCodeScan();
        $scan->setIpAddress('203.0.113.5');
        $scan->setIpAddress(null);

        self::assertNull($scan->getIpAddress());
    }

    /**
     * @test
     */
    public function it_allows_clearing_the_scanned_at_timestamp(): void
    {
        $scan = new QRCodeScan();
        $scan->setScannedAt(new \DateTimeImmutable());
        $scan->setScannedAt(null);

        self::assertNull($scan->getScannedAt());
    }

    /**
     * @test
     */
    public function it_truncates_a_multibyte_user_agent_by_characters_not_bytes(): void
    {
        // "é" is 2 bytes in UTF-8 but 1 character. Confirm truncation is character-based.
        $multibyte = str_repeat('é', QRCodeScan::USER_AGENT_MAX_LENGTH + 50);

        $scan = new QRCodeScan();
        $scan->setUserAgent($multibyte);

        $stored = $scan->getUserAgent();

        self::assertNotNull($stored);
        self::assertSame(QRCodeScan::USER_AGENT_MAX_LENGTH, mb_strlen($stored));
    }
}
