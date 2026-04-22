<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<ProductRelatedQRCodeInterface>
 */
interface ProductRelatedQRCodeFactoryInterface extends FactoryInterface
{
    public function createNew(): ProductRelatedQRCodeInterface;
}
