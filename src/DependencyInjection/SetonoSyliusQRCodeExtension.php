<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusQRCodeExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{redirect_type: int, utm: array{source: string, medium: string}, logo: array{path: string|null, size: int}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_qr_code.redirect_type', $config['redirect_type']);
        $container->setParameter('setono_sylius_qr_code.utm.source', $config['utm']['source']);
        $container->setParameter('setono_sylius_qr_code.utm.medium', $config['utm']['medium']);
        $container->setParameter('setono_sylius_qr_code.logo.path', $config['logo']['path']);
        $container->setParameter('setono_sylius_qr_code.logo.size', $config['logo']['size']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Resource and grid prepends are added in later phases (see §4–§5 of the change tasks).
    }
}
