<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Symfony\Component\HttpFoundation\Request;

final class QRCodeScannedEventTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_exposes_the_qr_code_and_request_passed_to_the_constructor(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();
        $request = new Request();

        $event = new QRCodeScannedEvent($qrCode, $request);

        self::assertSame($qrCode, $event->qrCode);
        self::assertSame($request, $event->request);
    }
}
