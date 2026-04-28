<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use function Symfony\Component\String\u;

class QRCodeScan implements QRCodeScanInterface
{
    public const USER_AGENT_MAX_LENGTH = 512;

    protected ?int $id = null;

    protected ?QRCodeInterface $qrCode = null;

    protected ?\DateTimeImmutable $scannedAt = null;

    protected ?string $ipAddress = null;

    protected ?string $userAgent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQrCode(): ?QRCodeInterface
    {
        return $this->qrCode;
    }

    public function setQrCode(?QRCodeInterface $qrCode): void
    {
        $this->qrCode = $qrCode;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(?\DateTimeImmutable $scannedAt): void
    {
        $this->scannedAt = $scannedAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = null === $userAgent ? null : u($userAgent)->truncate(self::USER_AGENT_MAX_LENGTH)->toString();
    }
}
