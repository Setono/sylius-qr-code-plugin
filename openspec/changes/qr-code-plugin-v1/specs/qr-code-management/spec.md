## ADDED Requirements

### Requirement: QR Code Resource Hierarchy

The plugin SHALL provide a Sylius resource model for QR codes using Doctrine Single Table Inheritance. A base `QRCode` resource SHALL persist shared fields and a `type` discriminator. Two concrete subtypes SHALL exist in v1: `ProductRelatedQRCode` (discriminator `product`) and `TargetUrlQRCode` (discriminator `target_url`). Each subtype SHALL be its own Sylius resource with its own form type. QR codes SHALL NOT carry a channel foreign key — they are channel-agnostic.

#### Scenario: Creating a product-related QR code persists the correct discriminator

- **WHEN** an admin creates a `ProductRelatedQRCode` and it is persisted
- **THEN** the row in `setono_sylius_qr_code__qr_code` has `type = 'product'` and the selected product is stored in the `product_id` column

#### Scenario: Creating a target-URL QR code persists the correct discriminator

- **WHEN** an admin creates a `TargetUrlQRCode` and it is persisted
- **THEN** the row in `setono_sylius_qr_code__qr_code` has `type = 'target_url'` and the supplied URL is stored in the `target_url` column

#### Scenario: Loading a QR code returns the correct subclass

- **WHEN** a QR code with `type = 'product'` is loaded from the repository
- **THEN** the returned object is an instance of `ProductRelatedQRCodeInterface`

### Requirement: QR Code Required Fields and Defaults

Every QR code SHALL have the following fields: `name`, `slug`, `redirectType`, `enabled`, `embedLogo`, `errorCorrectionLevel`, `utmSource`, `utmMedium`, `utmCampaign`, `createdAt`, `updatedAt`. When a QR code is created via the factory, default values SHALL be seeded from the plugin configuration: `redirectType` from `setono_sylius_qr_code.redirect_type`, `utmSource` from `setono_sylius_qr_code.utm.source`, `utmMedium` from `setono_sylius_qr_code.utm.medium`, `utmCampaign` from the slug (snapshot).

#### Scenario: Factory seeds redirect type from config

- **WHEN** the factory creates a new QR code entity and configuration sets `redirect_type: 302`
- **THEN** the new entity's `redirectType` is `302`

#### Scenario: utmCampaign snapshot does not follow later slug changes

- **WHEN** a QR code is created with slug `summer-sale` (so `utmCampaign = 'summer-sale'`), then the slug is later changed to `autumn-sale`
- **THEN** `utmCampaign` remains `summer-sale` unless explicitly changed by the admin

### Requirement: Slug Validation and Global Uniqueness

A QR code's `slug` SHALL match the regex `^[a-z0-9-]+$`. The `slug` SHALL be globally unique. A database unique constraint AND an application-level `UniqueSlug` validator SHALL both enforce this; the validator produces a human-readable form error before the DB constraint fires.

#### Scenario: Duplicate slug is rejected

- **WHEN** an admin attempts to save a QR code with a slug that already exists on another QR code
- **THEN** the form fails validation with a message indicating the slug is already taken, and no row is inserted

#### Scenario: Slug with invalid characters is rejected

- **WHEN** an admin submits a slug containing an uppercase letter, space, or special character (e.g. `Summer Sale!`)
- **THEN** the form fails validation with a regex-mismatch error

### Requirement: Forms Enforce Symfony Validation Before Persistence

Every admin create / update form SHALL be wired to Symfony's Validator such that a submit with invalid data is rejected with per-field error messages and is NEVER allowed to proceed to Doctrine flush. Database-level NOT NULL / UNIQUE / length constraints MUST NOT act as the first line of defence — they exist only as a safety net. In practice this means:

- The form type's `validation_groups` option and the `groups` option on each constraint SHALL be aligned so the constraints actually run when the form is submitted. A form declaring `validation_groups: ['foo']` against constraints in the default group is a configuration bug.
- Constraint violation messages SHALL live in the `validators` translation domain (not `messages`), since Symfony's validator resolves its translations there by default.

#### Scenario: Submitting an empty form shows field-level errors and does not touch the database

- **WHEN** an admin submits any QR code create form with blank required fields
- **THEN** the form re-renders with per-field validation error messages and no row is inserted into `setono_sylius_qr_code__qr_code`

#### Scenario: Database-level NOT NULL is not reachable from form submission

- **WHEN** an admin submits a form with any combination of missing required fields
- **THEN** the response is never a 500 caused by a SQL integrity constraint violation — Symfony validation catches it first

### Requirement: Subtype-Specific Validation

A `ProductRelatedQRCode` SHALL have a non-null `product` reference. A `TargetUrlQRCode` SHALL have a non-blank `targetUrl` that passes URL validation and is no longer than 2048 characters.

#### Scenario: Product QR code without a product is rejected

- **WHEN** an admin submits a product QR code form without selecting a product
- **THEN** validation fails with a "product required" message

#### Scenario: Target URL QR code with malformed URL is rejected

- **WHEN** an admin submits a target-URL QR code with value `not a url`
- **THEN** validation fails with a URL format error

### Requirement: Error Correction Level "Auto" Resolution

The admin form SHALL offer an "Auto" option for error correction level. At save time the factory or data mapper SHALL resolve "Auto" to `H` if `embedLogo = true`, otherwise to `M`. The stored value SHALL always be one of `L`, `M`, `Q`, `H`.

#### Scenario: Auto resolves to H when logo is embedded

- **WHEN** an admin saves a QR code with `errorCorrectionLevel = Auto` and `embedLogo = true`
- **THEN** the persisted `errorCorrectionLevel` is `H`

#### Scenario: Auto resolves to M when logo is not embedded

- **WHEN** an admin saves a QR code with `errorCorrectionLevel = Auto` and `embedLogo = false`
- **THEN** the persisted `errorCorrectionLevel` is `M`

### Requirement: Admin Grid with Unified List and Per-Type Creation

The plugin SHALL register a Sylius admin grid at `setono_sylius_qr_code_admin_qr_code` listing all QR codes regardless of subtype. The grid SHALL include columns: name, slug, type (with human label), scans count, enabled. The grid SHALL provide filters for name, slug, type, and enabled. The main "Create" action SHALL be a dropdown offering two options: "Product QR Code" (routes to `setono_sylius_qr_code_admin_product_related_qr_code_create`) and "URL QR Code" (routes to `setono_sylius_qr_code_admin_target_url_qr_code_create`). Row actions SHALL include: show, see stats, download, update, delete. Bulk action: delete.

#### Scenario: Admin sees both subtypes in a single grid

- **WHEN** an admin visits `/admin/qr-codes`
- **THEN** the grid lists both `ProductRelatedQRCode` and `TargetUrlQRCode` rows with the correct type label for each

#### Scenario: Update action redirects to subtype-specific route

- **WHEN** an admin clicks "Edit" on a product QR code row
- **THEN** the browser is redirected to the `ProductRelatedQRCode` update route, which renders the product-specific form

### Requirement: QR Code Show Page

The plugin SHALL expose `GET /admin/qr-codes/{id}` (name `setono_sylius_qr_code_admin_qr_code_show`) rendering a read-only detail page for a single QR code. The page SHALL show: the QR code's name, slug, type (human label), enabled state, timestamps (`createdAt`, `updatedAt`), redirect type (301/302/307), error correction level, the resolved public redirect URL, scans count, UTM parameters (source/medium/campaign), subtype-specific fields (linked product for `ProductRelatedQRCode`; `targetUrl` for `TargetUrlQRCode`), and a preview image. It SHALL also surface shortcuts to the existing update, delete, download, and stats actions for the same entity. Unknown ids SHALL return 404. The page SHALL be rendered by a subclass of Sylius's `ResourceController` (the plugin's `QRCodeController`) so adopting apps can override it via `classes.controller`; the template name SHALL follow Sylius's `@SyliusAdmin\Crud\show.html.twig` convention, allowing app-level `show.html.twig` overrides.

#### Scenario: Show page renders for a known QR code

- **WHEN** an admin navigates to `/admin/qr-codes/42` for an existing product QR code
- **THEN** the page renders with the QR's name, slug, enabled state, timestamps, UTM parameters, the linked product, a preview image, and shortcut buttons to update/delete/download/stats

#### Scenario: Show page renders the subtype-specific fields

- **WHEN** an admin opens the show page for a `TargetUrlQRCode`
- **THEN** the rendered detail view includes the `targetUrl` field and omits the product field (and vice versa for `ProductRelatedQRCode`)

#### Scenario: Show page for unknown id returns 404

- **WHEN** an admin navigates to `/admin/qr-codes/9999` with no such entity
- **THEN** the response status is 404

### Requirement: Admin Menu Integration

The plugin SHALL add a "QR Codes" entry under the Sylius admin "Marketing" menu section. The entry SHALL link to the QR code grid index and use the `qrcode` icon.

#### Scenario: Menu entry appears under Marketing

- **WHEN** an admin opens the Sylius admin sidebar
- **THEN** a "QR Codes" link is visible under the "Marketing" section

### Requirement: AJAX Name Generation Endpoint

The plugin SHALL expose `POST /admin/qr-codes/generate-name` which accepts either a `slug` or a `productId` parameter and returns a JSON object `{ "name": "..." }`. Given a slug, it returns `QR: <slug>`. Given a productId, it returns `QR: <product name>`. This endpoint SHALL only be accessible to authenticated admin users.

#### Scenario: Generate name from slug

- **WHEN** an admin's browser POSTs `{slug: "summer-sale"}` to the endpoint
- **THEN** the response is `{name: "QR: summer-sale"}`

#### Scenario: Generate name from product id

- **WHEN** an admin's browser POSTs `{productId: 42}` where product 42 has name "Summer T-Shirt"
- **THEN** the response is `{name: "QR: Summer T-Shirt"}`

#### Scenario: Unauthenticated access is denied

- **WHEN** an unauthenticated client POSTs to the endpoint
- **THEN** the response status is 401 or 403

### Requirement: Bulk Generation of Product QR Codes from Product Grid

The plugin SHALL add a bulk action named "Generate QR codes" to the `sylius_admin_product` grid. When triggered, a modal SHALL collect `embedLogo` and `enabled` options (no channel picker). The action SHALL create one `ProductRelatedQRCode` per selected product with `slug` derived from the product's base slug. Products whose slug is already taken by another QR code SHALL be skipped. A flash message SHALL report `Created X. Skipped Y (slug already exists)`.

#### Scenario: Bulk generate with no conflicts creates all QR codes

- **WHEN** an admin selects 5 products with no existing QR codes and submits the bulk modal
- **THEN** 5 new `ProductRelatedQRCode` rows exist and the flash reports `Created 5. Skipped 0.`

#### Scenario: Bulk generate skips products whose slug already exists

- **WHEN** an admin selects 3 products, 1 of which would derive a slug that is already used by another QR code, and submits the modal
- **THEN** 2 new rows are created, and the flash reports `Created 2. Skipped 1 (slug already exists).`

### Requirement: Product Deletion Cascades to QR Codes

The `ProductRelatedQRCode.product` foreign key SHALL declare `on-delete="CASCADE"`. Deleting a product SHALL remove its associated QR codes, which in turn cascades to their scan records.

#### Scenario: Deleting a product removes its QR codes and scans

- **WHEN** a product with a linked `ProductRelatedQRCode` (with recorded scans) is deleted
- **THEN** the QR code row and its `QRCodeScan` rows are removed from the database
