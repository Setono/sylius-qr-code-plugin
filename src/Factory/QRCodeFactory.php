<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;

final class QRCodeFactory implements QRCodeFactoryInterface
{
    /**
     * @param class-string<ProductRelatedQRCodeInterface> $productRelatedClass
     * @param class-string<TargetUrlQRCodeInterface>      $targetUrlClass
     */
    public function __construct(
        private readonly string $productRelatedClass,
        private readonly string $targetUrlClass,
        private readonly int $defaultRedirectType,
        private readonly ?string $defaultUtmSource,
        private readonly ?string $defaultUtmMedium,
    ) {
    }

    public function createProductRelated(): ProductRelatedQRCodeInterface
    {
        /** @var ProductRelatedQRCodeInterface $qrCode */
        $qrCode = new $this->productRelatedClass();
        $this->applyDefaults($qrCode);

        return $qrCode;
    }

    public function createTargetUrl(): TargetUrlQRCodeInterface
    {
        /** @var TargetUrlQRCodeInterface $qrCode */
        $qrCode = new $this->targetUrlClass();
        $this->applyDefaults($qrCode);

        return $qrCode;
    }

    private function applyDefaults(QRCodeInterface $qrCode): void
    {
        $qrCode->setRedirectType($this->defaultRedirectType);
        $qrCode->setUtmSource($this->defaultUtmSource);
        $qrCode->setUtmMedium($this->defaultUtmMedium);
    }
}
