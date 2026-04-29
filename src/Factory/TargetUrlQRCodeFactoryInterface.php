<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<TargetUrlQRCodeInterface>
 */
interface TargetUrlQRCodeFactoryInterface extends FactoryInterface
{
    public function createNew(): TargetUrlQRCodeInterface;
}
