<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Factory;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Shared base for the subtype factories. Not instantiated directly — the base QRCode
 * resource is abstract by convention (STI has two concrete subtypes).
 */
abstract class QRCodeFactory implements QRCodeFactoryInterface
{
    /**
     * @param FactoryInterface<QRCodeInterface> $decoratedFactory
     */
    public function __construct(
        protected readonly FactoryInterface $decoratedFactory,
        private readonly int $defaultRedirectType,
        private readonly ?string $defaultUtmSource,
        private readonly ?string $defaultUtmMedium,
    ) {
    }

    public function createNew(): QRCodeInterface
    {
        $qrCode = $this->decoratedFactory->createNew();
        Assert::isInstanceOf($qrCode, QRCodeInterface::class);

        $qrCode->setRedirectType($this->defaultRedirectType);
        $qrCode->setUtmSource($this->defaultUtmSource);
        $qrCode->setUtmMedium($this->defaultUtmMedium);

        return $qrCode;
    }
}
