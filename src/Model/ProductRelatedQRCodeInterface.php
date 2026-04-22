<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use Sylius\Component\Product\Model\ProductInterface;

interface ProductRelatedQRCodeInterface extends QRCodeInterface
{
    public function getProduct(): ?ProductInterface;

    public function setProduct(?ProductInterface $product): void;
}
