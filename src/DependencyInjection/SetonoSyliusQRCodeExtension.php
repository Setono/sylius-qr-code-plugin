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
        /** @var array{redirect_type: int, utm: array{source: string, medium: string}, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_qr_code.redirect_type', $config['redirect_type']);
        $container->setParameter('setono_sylius_qr_code.utm.source', $config['utm']['source']);
        $container->setParameter('setono_sylius_qr_code.utm.medium', $config['utm']['medium']);
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
            $this->prependProductGridBulkAction($container);
        }
    }

    private function prependProductGridBulkAction(ContainerBuilder $container): void
    {
        // Two prepends to make this work:
        //   (1) register the plugin's template for the custom bulk-action type `qr_code_generate`
        //       — without this, Sylius's bulk-action renderer looks up a nonexistent
        //       `@SyliusUi/Grid/BulkAction/qr_code_generate.html.twig` and 500s the product grid.
        //   (2) prepend the bulk action entry into the shipped `sylius_admin_product` grid.
        $container->prependExtensionConfig('sylius_grid', [
            'templates' => [
                'bulk_action' => [
                    'qr_code_generate' => '@SetonoSyliusQRCodePlugin/admin/qr_code/Grid/BulkAction/generate.html.twig',
                ],
            ],
            'grids' => [
                'sylius_admin_product' => [
                    'actions' => [
                        'bulk' => [
                            'generate_qr_codes' => [
                                'type' => 'qr_code_generate',
                                'label' => 'setono_sylius_qr_code.ui.generate_qr_codes',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function prependGrids(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_grid', [
            // Custom action type: behaves like Sylius's built-in `links` (a dropdown
            // of sub-links per row) but the template adds a `dropdown icon` caret so
            // the button visually signals that it opens a menu.
            'templates' => [
                'action' => [
                    'qr_code_download' => '@SetonoSyliusQRCodePlugin/admin/qr_code/grid/action/download.html.twig',
                ],
            ],
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
                            'type' => 'twig',
                            'label' => 'setono_sylius_qr_code.ui.name',
                            // Hand the whole entity to the template so it can render the name
                            // AND a trailing icon link to the public /qr/{slug} redirect URL.
                            // Sort still points at `name` so the column header works normally.
                            'path' => '.',
                            'sortable' => 'name',
                            'options' => [
                                'template' => '@SetonoSyliusQRCodePlugin/admin/qr_code/grid/field/name.html.twig',
                            ],
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
                                'type' => 'qr_code_download',
                                'label' => 'setono_sylius_qr_code.ui.download',
                                'options' => [
                                    'icon' => 'download',
                                    'links' => [
                                        'png' => [
                                            'label' => 'PNG',
                                            'icon' => 'image',
                                            'route' => 'setono_sylius_qr_code_admin_qr_code_download',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                                'format' => 'png',
                                            ],
                                        ],
                                        'svg' => [
                                            'label' => 'SVG',
                                            'icon' => 'image outline',
                                            'route' => 'setono_sylius_qr_code_admin_qr_code_download',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                                'format' => 'svg',
                                            ],
                                        ],
                                        'pdf' => [
                                            'label' => 'PDF',
                                            'icon' => 'file pdf',
                                            'route' => 'setono_sylius_qr_code_admin_qr_code_download',
                                            'parameters' => [
                                                'id' => 'resource.id',
                                                'format' => 'pdf',
                                            ],
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
