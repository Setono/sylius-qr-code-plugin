<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Form\Type;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Shared base form type for the two QR code subtypes. Holds fields common to both
 * (name, slug, embedLogo, enabled, and the advanced UTM/error-correction fields) and
 * installs a submit listener that resolves the UI-only `errorCorrectionLevel = auto`
 * value to `H` (logo embedded) or `M` (no logo) before the entity is flushed.
 */
abstract class QRCodeType extends AbstractResourceType
{
    /**
     * UI-only value; never stored on the entity.
     * Resolved to L|M|Q|H on submit based on the embedLogo flag.
     */
    public const ERROR_CORRECTION_LEVEL_AUTO = 'auto';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.name',
            ])
            ->add('slug', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.slug',
            ])
            ->add('embedLogo', CheckboxType::class, [
                'label' => 'setono_sylius_qr_code.ui.embed_logo',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'setono_sylius_qr_code.ui.enabled',
                'required' => false,
            ])
            ->add('errorCorrectionLevel', ChoiceType::class, [
                'label' => 'setono_sylius_qr_code.ui.error_correction_level',
                'choices' => [
                    'Auto' => self::ERROR_CORRECTION_LEVEL_AUTO,
                    'L' => QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW,
                    'M' => QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM,
                    'Q' => QRCodeInterface::ERROR_CORRECTION_LEVEL_QUARTILE,
                    'H' => QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH,
                ],
            ])
            ->add('utmSource', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.utm_source',
                'required' => false,
            ])
            ->add('utmMedium', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.utm_medium',
                'required' => false,
            ])
            ->add('utmCampaign', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.utm_campaign',
                'required' => false,
            ])
        ;

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!$data instanceof QRCodeInterface) {
                return;
            }

            if ($data->getErrorCorrectionLevel() !== self::ERROR_CORRECTION_LEVEL_AUTO) {
                return;
            }

            $data->setErrorCorrectionLevel(
                $data->isEmbedLogo()
                    ? QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH
                    : QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM,
            );
        });
    }
}
