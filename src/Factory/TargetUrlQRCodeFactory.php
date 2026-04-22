<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Webmozart\Assert\Assert;

final class TargetUrlQRCodeFactory extends QRCodeFactory implements TargetUrlQRCodeFactoryInterface
{
    public function createNew(): TargetUrlQRCodeInterface
    {
        $qrCode = parent::createNew();
        Assert::isInstanceOf($qrCode, TargetUrlQRCodeInterface::class);

        return $qrCode;
    }
}
