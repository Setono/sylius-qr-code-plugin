<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends FactoryInterface<QRCodeScanInterface>
 */
interface QRCodeScanFactoryInterface extends FactoryInterface
{
    public function createNew(): QRCodeScanInterface;

    /**
     * Creates a new scan for the given QR code, populated from the request (ip address,
     * user agent). `scannedAt` is populated by Gedmo Timestampable at flush time.
     */
    public function createFromRequest(QRCodeInterface $qrCode, Request $request): QRCodeScanInterface;
}
