<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\EventListener\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;

/**
 * Populates the Single Table Inheritance discriminator map on the base QRCode entity at the
 * moment Doctrine loads its metadata. The map key is each subtype's short class name
 * snake-cased; keys are stable across app-level subclassing because they are derived from the
 * plugin's own class names — they do not include any app namespace.
 *
 * The plugin's ORM XML declares `inheritance-type="SINGLE_TABLE"` and the discriminator column
 * on QRCode, but omits the map because the concrete subtype classes may be overridden by the
 * adopting application via `sylius_resource.resources.*.classes.model`. This listener resolves
 * the map from the resource config at runtime.
 */
final class QRCodeDiscriminatorMapListener
{
    /**
     * @param array<string, array{classes: array{model: class-string}}> $resources
     */
    public function __construct(
        private readonly array $resources,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() !== QRCode::class) {
            return;
        }

        $metadata->discriminatorMap = $this->buildDiscriminatorMap();
    }

    /**
     * @return array<string, class-string>
     */
    private function buildDiscriminatorMap(): array
    {
        $map = [];

        foreach ($this->resources as $resource) {
            if (!isset($resource['classes']['model'])) {
                continue;
            }

            $model = $resource['classes']['model'];

            if (!is_a($model, QRCodeInterface::class, true)) {
                continue;
            }

            $key = $this->getDiscriminatorKey($model);

            if (null === $key) {
                continue;
            }

            $map[$key] = $model;
        }

        return $map;
    }

    /**
     * @param class-string $class
     */
    private function getDiscriminatorKey(string $class): ?string
    {
        // The two subtype interfaces explicitly map to the discriminator values used throughout
        // the plugin (form field value, grid template, spec language). Anything that is not one
        // of the known subtypes is skipped — the base QRCode class itself is not in the map.
        if (is_a($class, ProductRelatedQRCodeInterface::class, true)) {
            return QRCodeInterface::TYPE_PRODUCT;
        }

        if (is_a($class, TargetUrlQRCodeInterface::class, true)) {
            return QRCodeInterface::TYPE_TARGET_URL;
        }

        return null;
    }
}
