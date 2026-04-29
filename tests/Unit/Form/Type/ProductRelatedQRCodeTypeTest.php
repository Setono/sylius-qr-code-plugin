<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Form\Type\ProductRelatedQRCodeType;
use Setono\SyliusQRCodePlugin\Form\Type\QRCodeType;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductAutocompleteChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Test\TypeTestCase;

final class ProductRelatedQRCodeTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private const LOGO_PATH = '/var/logos/qr.png';

    /**
     * @test
     */
    public function it_submits_valid_data_and_binds_it_to_the_entity(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();
        $this->productRepository->findOneBy(['code' => 'mug-123'])->willReturn($product);

        $entity = new ProductRelatedQRCode();

        $form = $this->factory->create(ProductRelatedQRCodeType::class, $entity);

        $form->submit([
            'name' => 'Mug QR',
            'slug' => 'mug-qr',
            'embedLogo' => '1',
            'enabled' => '1',
            'errorCorrectionLevel' => QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW,
            'utmSource' => 'pkg',
            'utmMedium' => 'sticker',
            'utmCampaign' => 'launch',
            'product' => 'mug-123',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());

        self::assertSame('Mug QR', $entity->getName());
        self::assertSame('mug-qr', $entity->getSlug());
        self::assertTrue($entity->isEmbedLogo());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW, $entity->getErrorCorrectionLevel());
        self::assertSame($product, $entity->getProduct());
    }

    /**
     * @test
     */
    public function it_resolves_auto_error_correction_level_to_medium_when_logo_not_embedded(): void
    {
        $entity = new ProductRelatedQRCode();

        $form = $this->factory->create(ProductRelatedQRCodeType::class, $entity);

        $form->submit($this->minimalSubmitData([
            'embedLogo' => null,
            'errorCorrectionLevel' => QRCodeType::ERROR_CORRECTION_LEVEL_AUTO,
        ]));

        self::assertTrue($form->isValid());
        self::assertFalse($entity->isEmbedLogo());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM, $entity->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_resolves_auto_error_correction_level_to_high_when_logo_embedded(): void
    {
        $entity = new ProductRelatedQRCode();

        $form = $this->factory->create(ProductRelatedQRCodeType::class, $entity);

        $form->submit($this->minimalSubmitData([
            'embedLogo' => '1',
            'errorCorrectionLevel' => QRCodeType::ERROR_CORRECTION_LEVEL_AUTO,
        ]));

        self::assertTrue($form->isValid());
        self::assertTrue($entity->isEmbedLogo());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH, $entity->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_does_not_touch_explicit_error_correction_level(): void
    {
        $entity = new ProductRelatedQRCode();

        $form = $this->factory->create(ProductRelatedQRCodeType::class, $entity);

        $form->submit($this->minimalSubmitData([
            'embedLogo' => null,
            'errorCorrectionLevel' => QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH,
        ]));

        self::assertTrue($form->isValid());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH, $entity->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_exposes_all_expected_fields(): void
    {
        $form = $this->factory->create(ProductRelatedQRCodeType::class, new ProductRelatedQRCode());

        $expected = [
            'name',
            'slug',
            'embedLogo',
            'enabled',
            'errorCorrectionLevel',
            'utmSource',
            'utmMedium',
            'utmCampaign',
            'product',
        ];

        foreach ($expected as $field) {
            self::assertTrue($form->has($field), sprintf('Form is missing the "%s" field.', $field));
        }
    }

    /**
     * @test
     */
    public function it_omits_the_embed_logo_field_when_no_logo_path_is_configured(): void
    {
        $factory = $this->buildFactoryWithoutLogo();

        $form = $factory->create(ProductRelatedQRCodeType::class, new ProductRelatedQRCode());

        self::assertFalse(
            $form->has('embedLogo'),
            'embedLogo must not be rendered when setono_sylius_qr_code.logo.path is null',
        );
    }

    /**
     * @test
     */
    public function it_uses_the_expected_block_prefix(): void
    {
        $type = new ProductRelatedQRCodeType(ProductRelatedQRCode::class);

        self::assertSame('setono_sylius_qr_code_product_related_qr_code', $type->getBlockPrefix());
    }

    /**
     * @test
     */
    public function it_configures_the_data_class(): void
    {
        $form = $this->factory->create(ProductRelatedQRCodeType::class, new ProductRelatedQRCode());

        self::assertSame(ProductRelatedQRCode::class, $form->getConfig()->getOption('data_class'));
    }

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface>
     *
     * @phpstan-ignore missingType.generics
     */
    private \Prophecy\Prophecy\ObjectProphecy $productRepository;

    /**
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $this->productRepository = $this->prophesize(RepositoryInterface::class);

        $registry = $this->prophesize(ServiceRegistryInterface::class);
        $registry->get('sylius.product')->willReturn($this->productRepository->reveal());

        return [
            new ProductRelatedQRCodeType(ProductRelatedQRCode::class, [], self::LOGO_PATH),
            new ProductAutocompleteChoiceType(),
            new ResourceAutocompleteChoiceType($registry->reveal()),
        ];
    }

    private function buildFactoryWithoutLogo(): \Symfony\Component\Form\FormFactoryInterface
    {
        $registry = $this->prophesize(ServiceRegistryInterface::class);
        $registry->get('sylius.product')->willReturn(
            $this->prophesize(RepositoryInterface::class)->reveal(),
        );

        return \Symfony\Component\Form\Forms::createFormFactoryBuilder()
            ->addType(new ProductRelatedQRCodeType(ProductRelatedQRCode::class, [], null))
            ->addType(new ProductAutocompleteChoiceType())
            ->addType(new ResourceAutocompleteChoiceType($registry->reveal()))
            ->getFormFactory()
        ;
    }

    /**
     * @param array<string, scalar|null> $overrides
     *
     * @return array<string, scalar|null>
     */
    private function minimalSubmitData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test',
            'slug' => 'test',
            'embedLogo' => null,
            'enabled' => '1',
            'errorCorrectionLevel' => QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM,
            'utmSource' => null,
            'utmMedium' => null,
            'utmCampaign' => null,
        ], $overrides);
    }
}
