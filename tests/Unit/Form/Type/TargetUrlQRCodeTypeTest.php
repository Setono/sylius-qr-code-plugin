<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Form\Type;

use Setono\SyliusQRCodePlugin\Form\Type\QRCodeType;
use Setono\SyliusQRCodePlugin\Form\Type\TargetUrlQRCodeType;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Symfony\Component\Form\Test\TypeTestCase;

final class TargetUrlQRCodeTypeTest extends TypeTestCase
{
    private const LOGO_PATH = '/var/logos/qr.png';

    /**
     * @test
     */
    public function it_submits_valid_data_and_binds_it_to_the_entity(): void
    {
        $entity = new TargetUrlQRCode();

        $form = $this->factory->create(TargetUrlQRCodeType::class, $entity);

        $form->submit([
            'name' => 'Promo poster',
            'slug' => 'promo-poster',
            'embedLogo' => '1',
            'enabled' => '1',
            'errorCorrectionLevel' => QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW,
            'utmSource' => 'flyer',
            'utmMedium' => 'print',
            'utmCampaign' => 'spring-2026',
            'targetUrl' => 'https://example.com/landing',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());

        self::assertSame('Promo poster', $entity->getName());
        self::assertSame('promo-poster', $entity->getSlug());
        self::assertTrue($entity->isEmbedLogo());
        self::assertTrue($entity->isEnabled());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW, $entity->getErrorCorrectionLevel());
        self::assertSame('flyer', $entity->getUtmSource());
        self::assertSame('print', $entity->getUtmMedium());
        self::assertSame('spring-2026', $entity->getUtmCampaign());
        self::assertSame('https://example.com/landing', $entity->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_resolves_auto_error_correction_level_to_medium_when_logo_not_embedded(): void
    {
        $entity = new TargetUrlQRCode();

        $form = $this->factory->create(TargetUrlQRCodeType::class, $entity);

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
        $entity = new TargetUrlQRCode();

        $form = $this->factory->create(TargetUrlQRCodeType::class, $entity);

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
        $entity = new TargetUrlQRCode();

        $form = $this->factory->create(TargetUrlQRCodeType::class, $entity);

        $form->submit($this->minimalSubmitData([
            'embedLogo' => '1',
            'errorCorrectionLevel' => QRCodeInterface::ERROR_CORRECTION_LEVEL_QUARTILE,
        ]));

        self::assertTrue($form->isValid());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_QUARTILE, $entity->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_exposes_all_expected_fields(): void
    {
        $form = $this->factory->create(TargetUrlQRCodeType::class, new TargetUrlQRCode());

        $expected = [
            'name',
            'slug',
            'embedLogo',
            'enabled',
            'errorCorrectionLevel',
            'utmSource',
            'utmMedium',
            'utmCampaign',
            'targetUrl',
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

        $form = $factory->create(TargetUrlQRCodeType::class, new TargetUrlQRCode());

        self::assertFalse(
            $form->has('embedLogo'),
            'embedLogo must not be rendered when setono_sylius_qr_code.logo.path is null',
        );
    }

    /**
     * @test
     */
    public function it_resolves_auto_error_correction_to_medium_even_when_entity_says_embed_logo_but_no_logo_is_configured(): void
    {
        // Even if a previously-saved entity has embedLogo=true, an admin editing it under a
        // deployment with no configured logo should not pay the cost of error-correction H —
        // the generator would silently skip the logo at render time anyway.
        $factory = $this->buildFactoryWithoutLogo();

        $entity = new TargetUrlQRCode();
        $entity->setEmbedLogo(true);

        $form = $factory->create(TargetUrlQRCodeType::class, $entity);

        $form->submit([
            'name' => 'Test',
            'slug' => 'test',
            'enabled' => '1',
            'errorCorrectionLevel' => QRCodeType::ERROR_CORRECTION_LEVEL_AUTO,
            'utmSource' => null,
            'utmMedium' => null,
            'utmCampaign' => null,
            'targetUrl' => 'https://example.com',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM, $entity->getErrorCorrectionLevel());
    }

    /**
     * @test
     */
    public function it_uses_the_expected_block_prefix(): void
    {
        $type = new TargetUrlQRCodeType(TargetUrlQRCode::class);

        self::assertSame('setono_sylius_qr_code_target_url_qr_code', $type->getBlockPrefix());
    }

    /**
     * @test
     */
    public function it_configures_the_data_class(): void
    {
        $form = $this->factory->create(TargetUrlQRCodeType::class, new TargetUrlQRCode());

        self::assertSame(TargetUrlQRCode::class, $form->getConfig()->getOption('data_class'));
    }

    /**
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        // Default factory used by tests in this case has a logo path configured — that's the
        // typical production setup. The "no logo" variant is built ad-hoc in the relevant tests
        // via {@see self::buildFactoryWithoutLogo()}.
        return [
            new TargetUrlQRCodeType(TargetUrlQRCode::class, [], self::LOGO_PATH),
        ];
    }

    private function buildFactoryWithoutLogo(): \Symfony\Component\Form\FormFactoryInterface
    {
        return \Symfony\Component\Form\Forms::createFormFactoryBuilder()
            ->addType(new TargetUrlQRCodeType(TargetUrlQRCode::class, [], null))
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
            'targetUrl' => 'https://example.com',
        ], $overrides);
    }
}
