<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\DependencyInjection;

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

        return $treeBuilder;
    }
}
