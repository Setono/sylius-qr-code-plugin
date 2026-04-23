<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Factory\QRCodeScanFactory;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class QRCodeScanFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function create_new_delegates_to_the_decorated_factory(): void
    {
        $scan = new QRCodeScan();

        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->shouldBeCalledOnce()->willReturn($scan);

        self::assertSame($scan, (new QRCodeScanFactory($decorated->reveal()))->createNew());
    }

    /**
     * @test
     */
    public function create_new_throws_when_decorated_factory_returns_wrong_type(): void
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn(new \stdClass());

        $this->expectException(\InvalidArgumentException::class);

        (new QRCodeScanFactory($decorated->reveal()))->createNew();
    }

    /**
     * @test
     */
    public function create_from_request_populates_ip_user_agent_and_qr_code(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();
        $scan = new QRCodeScan();

        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($scan);

        $request = Request::create('/qr/summer', server: [
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)',
        ]);

        $result = (new QRCodeScanFactory($decorated->reveal()))->createFromRequest($qrCode, $request);

        self::assertSame($scan, $result);
        self::assertSame($qrCode, $result->getQrCode());
        self::assertSame('203.0.113.5', $result->getIpAddress());
        self::assertSame('Mozilla/5.0 (iPhone)', $result->getUserAgent());
    }

    /**
     * @test
     */
    public function create_from_request_stores_unknown_when_client_ip_or_user_agent_is_missing(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();
        $scan = new QRCodeScan();

        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($scan);

        // `new Request()` skips the default 'Symfony' user-agent that `Request::create()`
        // would otherwise set, and leaves REMOTE_ADDR unset so getClientIp() returns null.
        $result = (new QRCodeScanFactory($decorated->reveal()))->createFromRequest($qrCode, new Request());

        self::assertSame('unknown', $result->getIpAddress());
        self::assertSame('unknown', $result->getUserAgent());
    }

    /**
     * @test
     */
    public function create_from_request_truncates_an_overlong_user_agent(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();
        $scan = new QRCodeScan();

        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($scan);

        $overlong = str_repeat('a', QRCodeScan::USER_AGENT_MAX_LENGTH + 50);
        $request = Request::create('/qr/x', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => $overlong,
        ]);

        $result = (new QRCodeScanFactory($decorated->reveal()))->createFromRequest($qrCode, $request);

        self::assertSame(QRCodeScan::USER_AGENT_MAX_LENGTH, mb_strlen((string) $result->getUserAgent()));
    }
}
