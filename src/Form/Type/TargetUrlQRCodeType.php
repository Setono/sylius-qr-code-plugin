<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

final class TargetUrlQRCodeType extends QRCodeType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('targetUrl', UrlType::class, [
            'label' => 'setono_sylius_qr_code.ui.target_url',
            'default_protocol' => 'https',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_qr_code_target_url_qr_code';
    }
}
