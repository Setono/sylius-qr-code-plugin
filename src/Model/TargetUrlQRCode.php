<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

class TargetUrlQRCode extends QRCode implements TargetUrlQRCodeInterface
{
    protected ?string $targetUrl = null;

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }
}
