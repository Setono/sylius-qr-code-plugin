<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusQRCodeExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{redirect_type: int, utm: array{source: string, medium: string}, logo: array{path: string|null, size: int}, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_qr_code.redirect_type', $config['redirect_type']);
        $container->setParameter('setono_sylius_qr_code.utm.source', $config['utm']['source']);
        $container->setParameter('setono_sylius_qr_code.utm.medium', $config['utm']['medium']);
        $container->setParameter('setono_sylius_qr_code.logo.path', $config['logo']['path']);
        $container->setParameter('setono_sylius_qr_code.logo.size', $config['logo']['size']);
        // Exposed for the discriminator-map listener — it needs the model class of each resource
        // so it can wire up the STI map at loadClassMetadata time.
        $container->setParameter('setono_sylius_qr_code.resources', $config['resources']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $this->registerResources(
            'setono_sylius_qr_code',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('kernel.bundles')) {
            return;
        }

        /** @var array<string, mixed> $bundles */
        $bundles = (array) $container->getParameter('kernel.bundles');

        if (isset($bundles['SyliusGridBundle'])) {
            $this->prependGrids($container);
        }
    }

    private function prependGrids(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_grid', [
            'grids' => [
                'setono_sylius_qr_code_admin_qr_code' => [
                    'driver' => [
                        'name' => 'doctrine/orm',
                        'options' => [
                            'class' => '%setono_sylius_qr_code.model.qr_code.class%',
                        ],
                    ],
                    'sorting' => [
                        'createdAt' => 'desc',
                    ],
                    'fields' => [
                        'name' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_qr_code.ui.name',
                            'sortable' => true,
                        ],
                        'slug' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_qr_code.ui.slug',
                            'sortable' => true,
                        ],
                        'type' => [
                            'type' => 'twig',
                            'label' => 'setono_sylius_qr_code.ui.type',
                            'options' => [
                                'template' => '@SetonoSyliusQRCodePlugin/admin/qr_code/grid/field/type.html.twig',
                            ],
                        ],
                        'scansCount' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_qr_code.ui.scans',
                        ],
                        'enabled' => [
                            'type' => 'twig',
                            'label' => 'sylius.ui.enabled',
                            'options' => [
                                'template' => '@SyliusUi/Grid/Field/enabled.html.twig',
                            ],
                        ],
                        'createdAt' => [
                            'type' => 'datetime',
                            'label' => 'sylius.ui.created_at',
                            'sortable' => null,
                        ],
                    ],
                    'filters' => [
                        'name' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_qr_code.ui.name',
                        ],
                        'slug' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_qr_code.ui.slug',
                        ],
                        'type' => [
                            'type' => 'select',
                            'label' => 'setono_sylius_qr_code.ui.type',
                            'form_options' => [
                                'choices' => [
                                    'setono_sylius_qr_code.ui.type_product' => 'product',
                                    'setono_sylius_qr_code.ui.type_target_url' => 'target_url',
                                ],
                            ],
                        ],
                        'enabled' => [
                            'type' => 'boolean',
                            'label' => 'sylius.ui.enabled',
                        ],
                    ],
                    'actions' => [
                        'main' => [
                            'create' => [
                                'type' => 'links',
                                'label' => 'sylius.ui.create',
                                'options' => [
                                    'class' => 'primary',
                                    'icon' => 'plus',
                                    'links' => [
                                        'product' => [
                                            'label' => 'setono_sylius_qr_code.ui.create_product_qr_code',
                                            'icon' => 'cube',
                                            'route' => 'setono_sylius_qr_code_admin_product_related_qr_code_create',
                                        ],
                                        'url' => [
                                            'label' => 'setono_sylius_qr_code.ui.create_url_qr_code',
                                            'icon' => 'linkify',
                                            'route' => 'setono_sylius_qr_code_admin_target_url_qr_code_create',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'item' => [
                            'show' => [
                                'type' => 'show',
                            ],
                            'stats' => [
                                'type' => 'default',
                                'label' => 'setono_sylius_qr_code.ui.see_stats',
                                'icon' => 'chart bar',
                                'options' => [
                                    'link' => [
                                        'route' => 'setono_sylius_qr_code_admin_qr_code_stats',
                                        'parameters' => [
                                            'id' => 'resource.id',
                                        ],
                                    ],
                                ],
                            ],
                            'download' => [
                                'type' => 'default',
                                'label' => 'setono_sylius_qr_code.ui.download',
                                'icon' => 'download',
                                'options' => [
                                    'link' => [
                                        'route' => 'setono_sylius_qr_code_admin_qr_code_download',
                                        'parameters' => [
                                            'id' => 'resource.id',
                                        ],
                                    ],
                                ],
                            ],
                            'update' => [
                                'type' => 'update',
                            ],
                            'delete' => [
                                'type' => 'delete',
                            ],
                        ],
                        'bulk' => [
                            'delete' => [
                                'type' => 'delete',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
