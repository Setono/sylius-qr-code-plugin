<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Webmozart\Assert\Assert;

final class ProductRelatedQRCodeFactory extends QRCodeFactory implements ProductRelatedQRCodeFactoryInterface
{
    public function createNew(): ProductRelatedQRCodeInterface
    {
        $qrCode = parent::createNew();
        Assert::isInstanceOf($qrCode, ProductRelatedQRCodeInterface::class);

        return $qrCode;
    }
}
