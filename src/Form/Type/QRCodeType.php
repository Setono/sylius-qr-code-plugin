<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Form\Type;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

abstract class QRCodeType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.name',
            ])
            ->add('slug', TextType::class, [
                'label' => 'setono_sylius_qr_code.ui.slug',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'setono_sylius_qr_code.ui.enabled',
                'required' => false,
            ])
            ->add('errorCorrectionLevel', ChoiceType::class, [
                'label' => 'setono_sylius_qr_code.ui.error_correction_level',
                'choices' => [
                    'setono_sylius_qr_code.ui.error_correction_level_low' => QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW,
                    'setono_sylius_qr_code.ui.error_correction_level_medium' => QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM,
                    'setono_sylius_qr_code.ui.error_correction_level_quartile' => QRCodeInterface::ERROR_CORRECTION_LEVEL_QUARTILE,
                    'setono_sylius_qr_code.ui.error_correction_level_high' => QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH,
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
    }
}
