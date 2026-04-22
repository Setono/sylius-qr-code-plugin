<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\EventListener;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $marketing = $event->getMenu()->getChild('marketing');

        if (null === $marketing) {
            return;
        }

        $marketing
            ->addChild('setono_sylius_qr_code', [
                'route' => 'setono_sylius_qr_code_admin_qr_code_index',
            ])
            ->setLabel('setono_sylius_qr_code.ui.qr_codes')
            ->setLabelAttribute('icon', 'qrcode')
        ;
    }
}
