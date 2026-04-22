<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

interface TargetUrlQRCodeInterface extends QRCodeInterface
{
    public function getTargetUrl(): ?string;

    public function setTargetUrl(?string $targetUrl): void;
}
