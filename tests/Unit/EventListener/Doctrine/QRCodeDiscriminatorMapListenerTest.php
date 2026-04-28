<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\EventListener\Doctrine\QRCodeDiscriminatorMapListener;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;

final class QRCodeDiscriminatorMapListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_populates_the_discriminator_map_from_the_resource_config(): void
    {
        $listener = new QRCodeDiscriminatorMapListener([
            'qr_code' => ['classes' => ['model' => QRCode::class]],
            'product_related_qr_code' => ['classes' => ['model' => ProductRelatedQRCode::class]],
            'target_url_qr_code' => ['classes' => ['model' => TargetUrlQRCode::class]],
            'qr_code_scan' => ['classes' => ['model' => QRCodeScan::class]],
        ]);

        $metadata = $this->metadataFor(QRCode::class);

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertSame([
            QRCodeInterface::TYPE_PRODUCT => ProductRelatedQRCode::class,
            QRCodeInterface::TYPE_TARGET_URL => TargetUrlQRCode::class,
        ], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_uses_app_level_subclasses_when_the_resource_config_overrides_them(): void
    {
        $appProductClass = new class() extends ProductRelatedQRCode {
        };
        $appTargetUrlClass = new class() extends TargetUrlQRCode {
        };

        $listener = new QRCodeDiscriminatorMapListener([
            'product_related_qr_code' => ['classes' => ['model' => $appProductClass::class]],
            'target_url_qr_code' => ['classes' => ['model' => $appTargetUrlClass::class]],
        ]);

        $metadata = $this->metadataFor(QRCode::class);

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertSame([
            QRCodeInterface::TYPE_PRODUCT => $appProductClass::class,
            QRCodeInterface::TYPE_TARGET_URL => $appTargetUrlClass::class,
        ], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_does_nothing_for_metadata_other_than_the_base_qr_code(): void
    {
        $listener = new QRCodeDiscriminatorMapListener([
            'product_related_qr_code' => ['classes' => ['model' => ProductRelatedQRCode::class]],
        ]);

        $metadata = $this->metadataFor(QRCodeScan::class);
        $metadata->discriminatorMap = ['unchanged' => QRCodeScan::class];

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertSame(['unchanged' => QRCodeScan::class], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_skips_resources_that_do_not_declare_a_model_class(): void
    {
        // The listener's input shape says `model` is always present, but the listener still
        // guards with `isset($resource['classes']['model'])` because the resource config is
        // populated at runtime from user-extensible config. Force the missing-model shape past
        // the type system here so we exercise that guard.
        /** @phpstan-ignore argument.type */
        $listener = new QRCodeDiscriminatorMapListener([
            'product_related_qr_code' => ['classes' => ['model' => ProductRelatedQRCode::class]],
            'something_else' => ['classes' => []],
        ]);

        $metadata = $this->metadataFor(QRCode::class);

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertSame([
            QRCodeInterface::TYPE_PRODUCT => ProductRelatedQRCode::class,
        ], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_skips_resources_whose_model_is_not_a_qr_code(): void
    {
        $listener = new QRCodeDiscriminatorMapListener([
            'qr_code_scan' => ['classes' => ['model' => QRCodeScan::class]],
            'target_url_qr_code' => ['classes' => ['model' => TargetUrlQRCode::class]],
        ]);

        $metadata = $this->metadataFor(QRCode::class);

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertSame([
            QRCodeInterface::TYPE_TARGET_URL => TargetUrlQRCode::class,
        ], $metadata->discriminatorMap);
    }

    /**
     * @test
     */
    public function it_skips_the_base_qr_code_itself(): void
    {
        // Base QRCode declares no subtype interface, so it must NOT appear in the map —
        // the map only contains real, dispatchable subtypes.
        $listener = new QRCodeDiscriminatorMapListener([
            'qr_code' => ['classes' => ['model' => QRCode::class]],
            'target_url_qr_code' => ['classes' => ['model' => TargetUrlQRCode::class]],
        ]);

        $metadata = $this->metadataFor(QRCode::class);

        $listener->loadClassMetadata($this->eventFor($metadata));

        self::assertArrayNotHasKey(QRCode::class, $metadata->discriminatorMap);
        self::assertNotContains(QRCode::class, $metadata->discriminatorMap);
    }

    /**
     * @param class-string $className
     *
     * @return ClassMetadata<object>
     */
    private function metadataFor(string $className): ClassMetadata
    {
        return new ClassMetadata($className);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function eventFor(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        return new LoadClassMetadataEventArgs($metadata, $entityManager->reveal());
    }
}
