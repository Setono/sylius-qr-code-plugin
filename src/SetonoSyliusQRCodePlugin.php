<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusQRCodePlugin\Resolver\CompositeTargetUrlResolver;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusQRCodePlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    /**
     * @return list<string>
     */
    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    protected function getModelNamespace(): string
    {
        return 'Setono\SyliusQRCodePlugin\Model';
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Every service tagged `setono_sylius_qr_code.target_url_resolver` is auto-registered
        // on the CompositeTargetUrlResolver. Subtype-specific resolvers throw
        // UnsupportedQRCodeException when they can't handle a given QR code; the composite
        // catches that and tries the next tagged service.
        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeTargetUrlResolver::class,
            'setono_sylius_qr_code.target_url_resolver',
        ));
    }
}
