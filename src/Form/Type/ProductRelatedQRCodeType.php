<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Form\Type;

use Sylius\Bundle\ProductBundle\Form\Type\ProductAutocompleteChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductRelatedQRCodeType extends QRCodeType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('product', ProductAutocompleteChoiceType::class, [
            'label' => 'setono_sylius_qr_code.ui.product',
            'multiple' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_qr_code_product_related_qr_code';
    }
}
