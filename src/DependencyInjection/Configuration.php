<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\DependencyInjection;

use Setono\SyliusQRCodePlugin\Controller\QRCodeController;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepository;
use Setono\SyliusQRCodePlugin\Repository\QRCodeScanRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_qr_code');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->integerNode('redirect_type')
                    ->defaultValue(307)
                    ->validate()
                        ->ifNotInArray([301, 302, 307])
                        ->thenInvalid('Invalid redirect_type %s; allowed values: 301, 302, 307.')
                    ->end()
                ->end()
                ->arrayNode('utm')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('source')->defaultValue('qr')->cannotBeEmpty()->end()
                        ->scalarNode('medium')->defaultValue('qrcode')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('logo')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path')->defaultNull()->end()
                        ->integerNode('size')
                            ->defaultValue(60)
                            ->min(0)
                            ->max(100)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('qr_code')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(QRCode::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(QRCodeInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(QRCodeController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(QRCodeRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('product_related_qr_code')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ProductRelatedQRCode::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(ProductRelatedQRCodeInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('target_url_qr_code')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(TargetUrlQRCode::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(TargetUrlQRCodeInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('qr_code_scan')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(QRCodeScan::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(QRCodeScanInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(QRCodeScanRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
