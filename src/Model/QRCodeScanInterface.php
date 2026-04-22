<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface QRCodeScanInterface extends ResourceInterface
{
    public function getQrCode(): ?QRCodeInterface;

    public function setQrCode(?QRCodeInterface $qrCode): void;

    public function getScannedAt(): ?\DateTimeImmutable;

    public function setScannedAt(?\DateTimeImmutable $scannedAt): void;

    public function getIpAddress(): ?string;

    public function setIpAddress(?string $ipAddress): void;

    public function getUserAgent(): ?string;

    public function setUserAgent(?string $userAgent): void;
}
