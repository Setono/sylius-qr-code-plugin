<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class QRCodeScanFactory implements QRCodeScanFactoryInterface
{
    /**
     * @param FactoryInterface<QRCodeScanInterface> $decoratedFactory
     */
    public function __construct(
        private readonly FactoryInterface $decoratedFactory,
    ) {
    }

    public function createNew(): QRCodeScanInterface
    {
        $scan = $this->decoratedFactory->createNew();
        Assert::isInstanceOf($scan, QRCodeScanInterface::class);

        return $scan;
    }

    public function createFromRequest(QRCodeInterface $qrCode, Request $request): QRCodeScanInterface
    {
        $scan = $this->createNew();

        $scan->setQrCode($qrCode);
        $scan->setIpAddress($request->getClientIp() ?? 'unknown');
        $scan->setUserAgent((string) $request->headers->get('User-Agent', 'unknown'));

        return $scan;
    }
}
