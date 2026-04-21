# SyliusQRCodePlugin Specification

**Version:** 1.0  
**Package:** `setono/sylius-qr-code-plugin`  
**Target Platform:** Sylius 1.12+, PHP 8.1+  
**Date:** December 2024

---

## 1. Overview

SyliusQRCodePlugin is a Sylius plugin that enables administrators to create, manage, and track QR codes directly from the admin panel. QR codes can link to any URL type (https, tel, mailto, etc.) or use internal slugs that redirect to configurable destinations.

### 1.1 Key Features

- Create QR codes with custom slugs accessible at `/qr/{slug}`
- Support for any URL scheme (https, tel, mailto, etc.)
- Link QR codes to Sylius products with automatic URL generation
- Comprehensive scan tracking and statistics
- Multiple output formats (PNG, SVG, PDF)
- Global logo embedding support
- Bulk generation from product grid
- Channel-aware redirects

---

## 2. Technical Foundation

### 2.1 Namespace & Structure

```
Setono\SyliusQRCodePlugin
├── Action/
│   ├── RedirectAction.php
│   └── Admin/
│       ├── DownloadAction.php
│       ├── GenerateNameAction.php
│       └── StatsAction.php
├── Controller/
│   └── QRCodeController.php
├── DependencyInjection/
│   ├── Configuration.php
│   └── SetonoSyliusQRCodeExtension.php
├── EventListener/
├── Form/
│   └── Type/
│       ├── ProductRelatedQRCodeType.php
│       └── TargetUrlQRCodeType.php
├── Generator/
│   ├── QRCodeGenerator.php
│   └── QRCodeGeneratorInterface.php
├── Menu/
│   └── AdminMenuListener.php
├── Model/
│   ├── QRCode.php
│   ├── QRCodeInterface.php
│   ├── QRCodeScan.php
│   ├── QRCodeScanInterface.php
│   ├── ProductRelatedQRCode.php
│   ├── ProductRelatedQRCodeInterface.php
│   ├── TargetUrlQRCode.php
│   └── TargetUrlQRCodeInterface.php
├── Repository/
│   ├── QRCodeRepository.php
│   ├── QRCodeRepositoryInterface.php
│   ├── QRCodeScanRepository.php
│   └── QRCodeScanRepositoryInterface.php
├── Resolver/
│   └── TargetUrlResolver.php
├── Storage/
│   └── QRCodeStorage.php
├── Tracker/
│   └── ScanTracker.php
└── SetonoSyliusQRCodePlugin.php
```

### 2.2 Bundle Class

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SetonoSyliusQRCodePlugin extends Bundle
{
}
```

### 2.3 Service Registration

Services use FQCN as service IDs with interfaces as aliases:

```yaml
services:
    Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator: ~
    
    Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface:
        alias: Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator
```

---

## 3. Entities

The plugin uses Doctrine Single Table Inheritance (STI) to differentiate between QR codes linking to products and QR codes linking to custom URLs.

### 3.1 Entity Hierarchy

```
QRCode (base entity)
├── ProductRelatedQRCode (discriminator: product)
└── TargetUrlQRCode (discriminator: target_url)
```

### 3.2 Interface Hierarchy

```
QRCodeInterface
├── ProductRelatedQRCodeInterface
└── TargetUrlQRCodeInterface
```

### 3.3 QRCode Entity (Base)

**Class:** `Setono\SyliusQRCodePlugin\Model\QRCode`  
**Interface:** `Setono\SyliusQRCodePlugin\Model\QRCodeInterface`  
**Table:** `setono_sylius_qr_code__qr_code`

Note: This class is not marked as `abstract` due to Sylius Resource Bundle limitations, but it should only be instantiated as one of the subclasses.

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `type` | string | Discriminator column (product, target_url) |
| `name` | string (255) | Admin display name (auto-generated, editable) |
| `slug` | string (100) | Unique identifier, used in `/qr/{slug}` route |
| `redirectType` | integer | HTTP redirect code (default: 307) |
| `enabled` | boolean | Whether QR code is active (default: true) |
| `embedLogo` | boolean | Whether to embed global logo (default: false) |
| `errorCorrectionLevel` | string | L, M, Q, or H (auto-set based on logo) |
| `channel` | Channel | Relation to Sylius Channel (required) |
| `utmSource` | string (50) | UTM source parameter (default: "qr") |
| `utmMedium` | string (50) | UTM medium parameter (default: "qrcode") |
| `utmCampaign` | string (100) | UTM campaign parameter (default: slug) |
| `createdAt` | datetime | Creation timestamp |
| `updatedAt` | datetime | Last modification timestamp |

**Constraints:**

- `slug` must be unique
- `slug` must match pattern: `^[a-z0-9-]+$`

### 3.4 ProductRelatedQRCode Entity

**Class:** `Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode`  
**Interface:** `Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface`  
**Discriminator value:** `product`

| Field | Type | Description |
|-------|------|-------------|
| `product` | Product | Relation to Sylius Product (required) |

### 3.5 TargetUrlQRCode Entity

**Class:** `Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode`  
**Interface:** `Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface`  
**Discriminator value:** `target_url`

| Field | Type | Description |
|-------|------|-------------|
| `targetUrl` | string (2048) | Destination URL (required) |

### 3.6 QRCodeScan Entity

**Class:** `Setono\SyliusQRCodePlugin\Model\QRCodeScan`  
**Interface:** `Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface`  
**Table:** `setono_sylius_qr_code__qr_code_scan`

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `qrCode` | QRCode | Relation to QRCode entity |
| `scannedAt` | datetime | Timestamp of scan |
| `ipAddress` | string (45) | IPv4 or IPv6 address |
| `userAgent` | string (512) | Full user agent string |
| `deviceType` | string (20) | mobile, tablet, desktop, unknown |
| `createdAt` | datetime | Record creation timestamp |

### 3.7 Doctrine Mappings

**Location:** `src/Resources/config/doctrine/model/`

**QRCode.orm.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                                      http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <mapped-superclass name="Setono\SyliusQRCodePlugin\Model\QRCode" table="setono_sylius_qr_code__qr_code">
        <id name="id" type="integer">
            <generator strategy="AUTO"/>
        </id>

        <field name="type" type="string" length="20"/>
        <field name="name" type="string" length="255"/>
        <field name="slug" type="string" length="100" unique="true"/>
        <field name="redirectType" type="integer"/>
        <field name="enabled" type="boolean"/>
        <field name="embedLogo" type="boolean"/>
        <field name="errorCorrectionLevel" type="string" length="1"/>
        <field name="utmSource" type="string" length="50" nullable="true"/>
        <field name="utmMedium" type="string" length="50" nullable="true"/>
        <field name="utmCampaign" type="string" length="100" nullable="true"/>
        <field name="createdAt" type="datetime_immutable"/>
        <field name="updatedAt" type="datetime_immutable"/>

        <many-to-one field="channel" target-entity="Sylius\Component\Channel\Model\ChannelInterface">
            <join-column name="channel_id" nullable="false" on-delete="CASCADE"/>
        </many-to-one>

        <one-to-many field="scans" target-entity="Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface" mapped-by="qrCode"/>
    </mapped-superclass>

</doctrine-mapping>
```

**ProductRelatedQRCode.orm.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                                      http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <mapped-superclass name="Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode">
        <many-to-one field="product" target-entity="Sylius\Component\Product\Model\ProductInterface">
            <join-column name="product_id" nullable="false" on-delete="CASCADE"/>
        </many-to-one>
    </mapped-superclass>

</doctrine-mapping>
```

**TargetUrlQRCode.orm.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                                      http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <mapped-superclass name="Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode">
        <field name="targetUrl" type="string" length="2048"/>
    </mapped-superclass>

</doctrine-mapping>
```

**Note:** The STI discriminator configuration is applied in the application's entity classes that extend these mapped superclasses:

```php
<?php
// Example application entity configuration

namespace App\Entity\QRCode;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusQRCodePlugin\Model\QRCode as BaseQRCode;

#[ORM\Entity]
#[ORM\Table(name: 'setono_sylius_qr_code__qr_code')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['product' => ProductRelatedQRCode::class, 'target_url' => TargetUrlQRCode::class])]
class QRCode extends BaseQRCode
{
}
```

---

## 4. Sylius Resource Configuration

### 4.1 Resource Definition

```php
// In SetonoSyliusQRCodeExtension::prepend()

$container->prependExtensionConfig('sylius_resource', [
    'resources' => [
        'setono_sylius_qr_code.qr_code' => [
            'classes' => [
                'model' => QRCode::class,
                'interface' => QRCodeInterface::class,
                'repository' => QRCodeRepository::class,
                'controller' => QRCodeController::class,
            ],
        ],
        'setono_sylius_qr_code.product_related_qr_code' => [
            'classes' => [
                'model' => ProductRelatedQRCode::class,
                'interface' => ProductRelatedQRCodeInterface::class,
                'form' => ProductRelatedQRCodeType::class,
            ],
        ],
        'setono_sylius_qr_code.target_url_qr_code' => [
            'classes' => [
                'model' => TargetUrlQRCode::class,
                'interface' => TargetUrlQRCodeInterface::class,
                'form' => TargetUrlQRCodeType::class,
            ],
        ],
        'setono_sylius_qr_code.qr_code_scan' => [
            'classes' => [
                'model' => QRCodeScan::class,
                'interface' => QRCodeScanInterface::class,
                'repository' => QRCodeScanRepository::class,
            ],
        ],
    ],
]);
```

### 4.2 Grid Configuration

```php
// In SetonoSyliusQRCodeExtension::prepend()

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
                'channel' => [
                    'type' => 'string',
                    'label' => 'sylius.ui.channel',
                    'path' => 'channel.name',
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
                'channel' => [
                    'type' => 'entity',
                    'label' => 'sylius.ui.channel',
                    'form_options' => [
                        'class' => '%sylius.model.channel.class%',
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
```

---

## 5. Routes

### 5.1 Frontend Routes

**File:** `src/Resources/config/routes/frontend.yaml`

```yaml
setono_sylius_qr_code_redirect:
    path: /qr/{slug}
    controller: Setono\SyliusQRCodePlugin\Action\RedirectAction
    methods: [GET]
```

### 5.2 Admin Routes

**File:** `src/Resources/config/routes/admin.yaml`

```yaml
# Base QR Code resource (index, update redirect, delete)
setono_sylius_qr_code_admin_qr_code:
    resource: |
        alias: setono_sylius_qr_code.qr_code
        section: admin
        templates: "@SetonoSyliusQRCodePlugin/admin/qr_code"
        redirect: index
        grid: setono_sylius_qr_code_admin_qr_code
        vars:
            all:
                subheader: setono_sylius_qr_code.ui.manage_qr_codes
            index:
                icon: qrcode
        except: ['create']
    type: sylius.resource
    prefix: /admin/qr-codes

# ProductRelatedQRCode resource
setono_sylius_qr_code_admin_product_related_qr_code:
    resource: |
        alias: setono_sylius_qr_code.product_related_qr_code
        section: admin
        templates: "@SetonoSyliusQRCodePlugin/admin/qr_code"
        redirect: setono_sylius_qr_code_admin_qr_code_index
        only: ['create', 'update']
        vars:
            all:
                subheader: setono_sylius_qr_code.ui.product_qr_code
    type: sylius.resource
    prefix: /admin/product-related-qr-codes

# TargetUrlQRCode resource
setono_sylius_qr_code_admin_target_url_qr_code:
    resource: |
        alias: setono_sylius_qr_code.target_url_qr_code
        section: admin
        templates: "@SetonoSyliusQRCodePlugin/admin/qr_code"
        redirect: setono_sylius_qr_code_admin_qr_code_index
        only: ['create', 'update']
        vars:
            all:
                subheader: setono_sylius_qr_code.ui.url_qr_code
    type: sylius.resource
    prefix: /admin/target-url-qr-codes

# Custom routes
setono_sylius_qr_code_admin_qr_code_stats:
    path: /admin/qr-codes/{id}/stats
    controller: Setono\SyliusQRCodePlugin\Action\Admin\StatsAction
    methods: [GET]

setono_sylius_qr_code_admin_qr_code_download:
    path: /admin/qr-codes/{id}/download/{format}
    controller: Setono\SyliusQRCodePlugin\Action\Admin\DownloadAction
    methods: [GET]
    defaults:
        format: png
    requirements:
        format: png|svg|pdf

setono_sylius_qr_code_admin_qr_code_generate_name:
    path: /admin/qr-codes/generate-name
    controller: Setono\SyliusQRCodePlugin\Action\Admin\GenerateNameAction
    methods: [POST]
```

### 5.3 Custom Controller for Update Redirect

**Class:** `Setono\SyliusQRCodePlugin\Controller\QRCodeController`

The base QR code resource uses a custom controller that redirects update requests to the correct subclass route based on the entity type.

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class QRCodeController extends ResourceController
{
    public function updateAction(Request $request): Response
    {
        $resource = $this->findOr404($request);

        if ($resource instanceof ProductRelatedQRCodeInterface) {
            return $this->redirectToRoute('setono_sylius_qr_code_admin_product_related_qr_code_update', [
                'id' => $resource->getId(),
            ]);
        }

        if ($resource instanceof TargetUrlQRCodeInterface) {
            return $this->redirectToRoute('setono_sylius_qr_code_admin_target_url_qr_code_update', [
                'id' => $resource->getId(),
            ]);
        }

        throw new \LogicException('Unknown QR code type');
    }
}

---

## 6. Redirect Logic

### 6.1 RedirectAction

**Class:** `Setono\SyliusQRCodePlugin\Action\RedirectAction`

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Action;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RedirectAction
{
    public function __invoke(Request $request, string $slug): Response
    {
        // 1. Resolve channel from request hostname
        // 2. Find QRCode by slug and channel
        // 3. If not found or disabled → throw NotFoundHttpException
        // 4. If ProductRelatedQRCode and product is deleted/disabled → throw NotFoundHttpException
        // 5. Track scan (synchronous insert)
        // 6. Resolve target URL via TargetUrlResolver
        // 7. Return RedirectResponse with configured status code
    }
}
```

### 6.2 Target URL Resolution

**Class:** `Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolver`

Resolves the destination URL based on the QR code type:

- For `TargetUrlQRCode`: use the `targetUrl` property directly
- For `ProductRelatedQRCode`: generate URL using `sylius_shop_product_show` route

```php
public function resolve(QRCodeInterface $qrCode): string
{
    if ($qrCode instanceof TargetUrlQRCodeInterface) {
        return $this->appendUtmParameters($qrCode->getTargetUrl(), $qrCode);
    }

    if ($qrCode instanceof ProductRelatedQRCodeInterface) {
        $product = $qrCode->getProduct();
        $channel = $qrCode->getChannel();

        $url = $this->urlGenerator->generate('sylius_shop_product_show', [
            'slug' => $product->getSlug(),
            '_locale' => $channel->getDefaultLocale()->getCode(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->appendUtmParameters($url, $qrCode);
    }

    throw new \LogicException('Unknown QR code type');
}

private function appendUtmParameters(string $url, QRCodeInterface $qrCode): string
{
    $parts = parse_url($url);
    $query = [];
    
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    if (null !== $qrCode->getUtmSource()) {
        $query['utm_source'] = $qrCode->getUtmSource();
    }
    if (null !== $qrCode->getUtmMedium()) {
        $query['utm_medium'] = $qrCode->getUtmMedium();
    }
    if (null !== $qrCode->getUtmCampaign()) {
        $query['utm_campaign'] = $qrCode->getUtmCampaign();
    }

    $parts['query'] = http_build_query($query);

    return $this->buildUrl($parts);
}
```

---

## 7. Admin Interface

### 7.1 Menu Integration

**Class:** `Setono\SyliusQRCodePlugin\Menu\AdminMenuListener`

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $marketing = $event->getMenu()->getChild('marketing');

        if (null === $marketing) {
            return;
        }

        $marketing->addChild('qr_codes', [
            'route' => 'setono_sylius_qr_code_admin_qr_code_index',
        ])
            ->setLabel('setono_sylius_qr_code.ui.qr_codes')
            ->setLabelAttribute('icon', 'qrcode');
    }
}
```

### 7.2 Form Types

Each subclass has its own form type.

**Class:** `Setono\SyliusQRCodePlugin\Form\Type\ProductRelatedQRCodeType`

**Fields:**

- `name` - TextType (auto-populated via AJAX)
- `slug` - TextType (triggers name generation on blur)
- `product` - EntityType with Sylius product autocomplete (required)
- `channel` - ChannelChoiceType
- `embedLogo` - CheckboxType
- `enabled` - CheckboxType

**Advanced Settings (collapsible):**

- `redirectType` - ChoiceType (301, 302, 303, 307, 308)
- `utmSource` - TextType
- `utmMedium` - TextType
- `utmCampaign` - TextType
- `errorCorrectionLevel` - ChoiceType (Auto, L, M, Q, H)

**Class:** `Setono\SyliusQRCodePlugin\Form\Type\TargetUrlQRCodeType`

**Fields:**

- `name` - TextType (auto-populated via AJAX)
- `slug` - TextType (triggers name generation on blur)
- `targetUrl` - UrlType (required)
- `channel` - ChannelChoiceType
- `embedLogo` - CheckboxType
- `enabled` - CheckboxType

**Advanced Settings (collapsible):**

- `redirectType` - ChoiceType (301, 302, 303, 307, 308)
- `utmSource` - TextType
- `utmMedium` - TextType
- `utmCampaign` - TextType
- `errorCorrectionLevel` - ChoiceType (Auto, L, M, Q, H)

### 7.3 AJAX Name Generation

**Endpoint:** `POST /admin/qr-codes/generate-name`

**Request:**
```json
{ "slug": "product-1" }
// or
{ "productId": 123 }
```

**Response:**
```json
{ "name": "QR: product-1" }
// or
{ "name": "QR: Product Name" }
```

**JavaScript behavior:**

- On slug field blur → request with slug
- On product selection → request with productId
- Populate name field but keep it editable

### 7.4 Statistics Page

**Route:** `setono_sylius_qr_code_admin_qr_code_stats`  
**Template:** `@SetonoSyliusQRCodePlugin/admin/qr_code/stats.html.twig`

**Header section:**

- QR code name and preview image (download buttons: PNG, SVG, PDF)
- Total scans (all time)
- Quick stats cards: Last 7 days, Last 30 days

**Time period filter:**

- Preset buttons: 7 days, 30 days, 90 days
- Updates charts via AJAX

**Charts (Chart.js):**

1. **Scans Over Time** - Line chart
   - X-axis: Date
   - Y-axis: Scan count
   - Granularity: Daily for ≤30 days, weekly for longer

2. **Device Breakdown** - Doughnut chart
   - Segments: Mobile, Tablet, Desktop, Unknown

**Data table:**

- Paginated list of recent scans
- Columns: Date/Time, Device Type, IP (partial), User Agent (truncated)
- Export CSV button

---

## 8. Product Integration

### 8.1 Product Grid Bulk Action

**Location:** Admin → Catalog → Products grid

Add bulk action via grid configuration extension:

```php
// In SetonoSyliusQRCodeExtension::prepend()

$container->prependExtensionConfig('sylius_grid', [
    'grids' => [
        'sylius_admin_product' => [
            'actions' => [
                'bulk' => [
                    'generate_qr_codes' => [
                        'type' => 'default',
                        'label' => 'setono_sylius_qr_code.ui.generate_qr_codes',
                        'icon' => 'qrcode',
                        'options' => [
                            'link' => [
                                'route' => 'setono_sylius_qr_code_admin_bulk_generate',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
]);
```

### 8.2 Bulk Generation Modal

**Sylius-style modal with options:**

- Channel selector (required)
- Embed logo (checkbox)
- Enabled by default (checkbox, default: checked)

**Process:**

1. Validate each product slug doesn't conflict with existing QR codes
2. Skip products with conflicts
3. Create QRCode entities:
   - `slug` = product slug
   - `name` = "QR: {product name}"
   - `product` = selected product
   - `channel` = selected channel
4. Show result: "Created X QR codes. Skipped Y (slug already exists)."

---

## 9. QR Code Generation

### 9.1 Generator Service

**Class:** `Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator`  
**Interface:** `Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface`

**Library:** `endroid/qr-code` ^5.0

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Generator;

interface QRCodeGeneratorInterface
{
    public function generatePng(QRCodeInterface $qrCode, int $size = 1200): string;

    public function generateSvg(QRCodeInterface $qrCode): string;

    public function generatePdf(QRCodeInterface $qrCode): string;
}
```

### 9.2 Output Formats

| Format | Size/Specs | Use Case |
|--------|------------|----------|
| PNG | 1200px | Web, print (users resize as needed) |
| SVG | Vector | Scalable for any use |
| PDF | A4, QR centered | Print-ready |

### 9.3 Generation Settings

- Foreground: Black (#000000)
- Background: White (#FFFFFF)
- Margin: 10 units
- Error correction:
  - Default: M (Medium)
  - With logo: H (High) - forced
  - Configurable: L, M, Q, H

### 9.4 Logo Embedding

Configured via plugin configuration (YAML only):

```yaml
setono_sylius_qr_code:
    logo:
        path: '%kernel.project_dir%/public/images/qr-logo.png'
        size: 60  # Percentage of QR code size (0-100)
```

When `embedLogo` is true on QRCode entity, the logo is centered on the QR code.

---

## 10. Storage

### 10.1 Flysystem Integration

QR code images are stored using Flysystem:

```yaml
# config/packages/flysystem.yaml (user configuration)
flysystem:
    storages:
        setono_sylius_qr_code.storage:
            adapter: 'local'
            options:
                directory: '%kernel.project_dir%/public/media/qr-codes'
```

**Storage class:** `Setono\SyliusQRCodePlugin\Storage\QRCodeStorage`

**File structure:**
```
/media/qr-codes/
  /{qr_code_id}/
    qr.png
    qr.svg
    qr.pdf
```

### 10.2 Regeneration Triggers

Images are regenerated when:

- QR code entity is created
- QR code entity is updated (slug change, logo embedding toggle)
- Global logo configuration changes (regenerate all with embedLogo = true)

---

## 11. Scan Tracking

### 11.1 Tracker Service

**Class:** `Setono\SyliusQRCodePlugin\Tracker\ScanTracker`

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tracker;

use Symfony\Component\HttpFoundation\Request;

final class ScanTracker
{
    public function track(QRCodeInterface $qrCode, Request $request): void
    {
        $scan = new QRCodeScan();
        $scan->setQrCode($qrCode);
        $scan->setScannedAt(new \DateTimeImmutable());
        $scan->setIpAddress($request->getClientIp() ?? 'unknown');
        $scan->setUserAgent($request->headers->get('User-Agent', ''));
        $scan->setDeviceType($this->deviceDetector->detect($request));

        $this->entityManager->persist($scan);
        $this->entityManager->flush();
    }
}
```

### 11.2 Device Detection

**Library:** `matomo/device-detector` ^6.0

Detects: mobile, tablet, desktop, unknown

---

## 12. Configuration

### 12.1 Plugin Configuration

**Configuration class:** `Setono\SyliusQRCodePlugin\DependencyInjection\Configuration`

```yaml
# config/packages/setono_sylius_qr_code.yaml
setono_sylius_qr_code:
    redirect_type: 307
    utm:
        enabled: true
        source: 'qr'
        medium: 'qrcode'
    logo:
        path: null  # Optional: path to logo image
        size: 60    # Percentage of QR code size
```

---

## 13. Translations

**Location:** `src/Resources/translations/messages.en.yaml`

```yaml
setono_sylius_qr_code:
    ui:
        qr_codes: QR Codes
        qr_code: QR Code
        manage_qr_codes: Manage QR codes
        name: Name
        slug: Slug
        type: Type
        type_product: Product
        type_target_url: URL
        target_url: Target URL
        product: Product
        channel: Channel
        embed_logo: Embed logo
        enabled: Enabled
        scans: Scans
        see_stats: See stats
        download: Download
        generate_qr_codes: Generate QR codes
        create_product_qr_code: Product QR Code
        create_url_qr_code: URL QR Code
        product_qr_code: Product QR Code
        url_qr_code: URL QR Code
        redirect_type: Redirect type
        error_correction_level: Error correction level
        utm_source: UTM Source
        utm_medium: UTM Medium
        utm_campaign: UTM Campaign
        advanced_settings: Advanced settings
        statistics: Statistics
        total_scans: Total scans
        last_7_days: Last 7 days
        last_30_days: Last 30 days
        last_90_days: Last 90 days
        scans_over_time: Scans over time
        device_breakdown: Device breakdown
        recent_scans: Recent scans
        export_csv: Export CSV
        date_time: Date/Time
        device_type: Device type
        ip_address: IP Address
        user_agent: User Agent
        created: Created
        skipped: Skipped
        slug_already_exists: slug already exists
```

---

## 14. Templates

### 14.1 Template Structure

```
src/Resources/views/
├── admin/
│   └── qr_code/
│       ├── _form.html.twig
│       ├── create.html.twig
│       ├── index.html.twig
│       ├── update.html.twig
│       ├── stats.html.twig
│       ├── _bulkGenerateModal.html.twig
│       └── grid/
│           └── field/
│               └── type.html.twig
└── frontend/
    └── 404.html.twig (optional custom 404)
```

**grid/field/type.html.twig:**
```twig
{% if data == 'product' %}
    <span class="ui label">{{ 'setono_sylius_qr_code.ui.type_product'|trans }}</span>
{% elseif data == 'target_url' %}
    <span class="ui label">{{ 'setono_sylius_qr_code.ui.type_target_url'|trans }}</span>
{% endif %}
```

---

## 15. Dependencies

### 15.1 composer.json

```json
{
    "name": "setono/sylius-qr-code-plugin",
    "type": "sylius-plugin",
    "description": "QR code generation and tracking for Sylius",
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "sylius/sylius": "^1.12",
        "endroid/qr-code": "^5.0",
        "matomo/device-detector": "^6.0",
        "league/flysystem": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpspec/prophecy-phpunit": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Setono\\SyliusQRCodePlugin\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Setono\\SyliusQRCodePlugin\\Tests\\": "tests/"
        }
    }
}
```

---

## 16. Testing

### 16.1 Test Structure

```
tests/
├── Unit/
│   ├── Generator/
│   │   └── QRCodeGeneratorTest.php
│   ├── Resolver/
│   │   └── TargetUrlResolverTest.php
│   └── Tracker/
│       └── ScanTrackerTest.php
├── Integration/
│   └── Repository/
│       └── QRCodeRepositoryTest.php
└── Functional/
    ├── Action/
    │   └── RedirectActionTest.php
    └── Admin/
        └── QRCodeCrudTest.php
```

### 16.2 Test Categories

**Unit Tests:**

- QRCodeGenerator: Image generation with various settings
- TargetUrlResolver: URL building, UTM parameter merging
- DeviceDetector wrapper: User-agent parsing

**Integration Tests:**

- Repository queries: Statistics aggregation, filtering

**Functional Tests:**

- RedirectAction: Scan logging, redirect behavior, 404 handling
- Admin CRUD: Create, update, delete operations
- Bulk generation: Product grid action

---

## 17. Events

### 17.1 Dispatched Events

| Event Class | Trigger |
|-------------|---------|
| `QRCodeCreatedEvent` | After QR code entity is persisted |
| `QRCodeUpdatedEvent` | After QR code entity is updated |
| `QRCodeScannedEvent` | After scan is tracked |

**Example event class:**

```php
<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Event;

final class QRCodeScannedEvent
{
    public function __construct(
        public readonly QRCodeInterface $qrCode,
        public readonly QRCodeScanInterface $scan,
    ) {
    }
}
```

---

## 18. Validation

### 18.1 QRCode Constraints (Base)

**File:** `src/Resources/config/validation/QRCode.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<constraint-mapping xmlns="http://symfony.com/schema/dic/constraint-mapping">
    <class name="Setono\SyliusQRCodePlugin\Model\QRCode">
        <property name="name">
            <constraint name="NotBlank"/>
            <constraint name="Length">
                <option name="max">255</option>
            </constraint>
        </property>
        <property name="slug">
            <constraint name="NotBlank"/>
            <constraint name="Length">
                <option name="max">100</option>
            </constraint>
            <constraint name="Regex">
                <option name="pattern">/^[a-z0-9-]+$/</option>
            </constraint>
        </property>
        <constraint name="Setono\SyliusQRCodePlugin\Validator\Constraints\UniqueSlugPerChannel"/>
    </class>
</constraint-mapping>
```

### 18.2 ProductRelatedQRCode Constraints

**File:** `src/Resources/config/validation/ProductRelatedQRCode.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<constraint-mapping xmlns="http://symfony.com/schema/dic/constraint-mapping">
    <class name="Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode">
        <property name="product">
            <constraint name="NotNull"/>
        </property>
    </class>
</constraint-mapping>
```

### 18.3 TargetUrlQRCode Constraints

**File:** `src/Resources/config/validation/TargetUrlQRCode.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<constraint-mapping xmlns="http://symfony.com/schema/dic/constraint-mapping">
    <class name="Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode">
        <property name="targetUrl">
            <constraint name="NotBlank"/>
            <constraint name="Url"/>
            <constraint name="Length">
                <option name="max">2048</option>
            </constraint>
        </property>
    </class>
</constraint-mapping>
```

### 18.4 Custom Validators

**UniqueSlugPerChannel:** Ensures slug is unique within a channel

---

## Appendix A: Example Flows

### A.1 Creating a URL QR Code

1. Admin navigates to Marketing → QR Codes
2. Clicks "Create" dropdown → "URL QR Code"
3. Enters slug: `summer-sale`
4. Name auto-populates: "QR: summer-sale" (editable)
5. Enters URL: `https://example.com/promotions/summer`
6. Selects Channel: "Default"
7. Enables "Embed Logo"
8. Saves
9. QR code images are generated and stored via Flysystem
10. QR code is accessible at `https://example.com/qr/summer-sale`

### A.2 Creating a Product QR Code

1. Admin navigates to Marketing → QR Codes
2. Clicks "Create" dropdown → "Product QR Code"
3. Selects Product: "Summer T-Shirt" (via autocomplete)
4. Slug auto-populates from product: `summer-t-shirt`
5. Name auto-populates: "QR: Summer T-Shirt" (editable)
6. Selects Channel: "Default"
7. Saves
8. QR code images are generated
9. QR code redirects to the product page with UTM parameters

### A.3 Bulk Generating QR Codes for Products

1. Admin navigates to Catalog → Products
2. Filters by taxon "Featured"
3. Selects 10 products via checkboxes
4. Clicks "Generate QR Codes" bulk action
5. Sylius modal appears:
   - Selects Channel: "Default"
   - Checks "Embed Logo"
6. Clicks "Generate"
7. System validates slugs, creates `ProductRelatedQRCode` entities
8. Success message: "Created 10 QR codes. Skipped 0."

### A.4 Viewing Statistics

1. Admin navigates to Marketing → QR Codes
2. Clicks "See Stats" on a QR code row
3. New page opens with statistics
4. Views line chart of scans over last 30 days
5. Views doughnut chart: 60% mobile, 35% desktop, 5% tablet
6. Clicks "Export CSV" to download scan data

---

## Appendix B: Future Considerations (Out of Scope v1.0)

- Scheduling and expiration dates
- Folder/tag organization
- Role-based permissions
- REST API endpoints
- Custom QR code colors
- Per-QR-code logo upload
- Locale detection for product redirects
- Geolocation tracking (country/city via IP)
- A/B testing with multiple destinations
- Short URL generation
- "Download all formats" ZIP option
