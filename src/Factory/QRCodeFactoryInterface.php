<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;

interface QRCodeFactoryInterface
{
    public function createProductRelated(): ProductRelatedQRCodeInterface;

    public function createTargetUrl(): TargetUrlQRCodeInterface;
}
