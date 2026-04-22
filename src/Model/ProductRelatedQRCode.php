<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use Sylius\Component\Product\Model\ProductInterface;

class ProductRelatedQRCode extends QRCode implements ProductRelatedQRCodeInterface
{
    protected ?ProductInterface $product = null;

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }
}
