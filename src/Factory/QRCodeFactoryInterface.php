<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<QRCodeInterface>
 */
interface QRCodeFactoryInterface extends FactoryInterface
{
    public function createNew(): QRCodeInterface;
}
