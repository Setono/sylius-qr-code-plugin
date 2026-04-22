## 1. Project Scaffolding

- [x] 1.1 Update `composer.json` (name `setono/sylius-qr-code-plugin`, description, autoload `Setono\\SyliusQRCodePlugin\\` → `src/`, autoload-dev `Setono\\SyliusQRCodePlugin\\Tests\\` → `tests/`)
- [x] 1.2 Add runtime dependencies: `endroid/qr-code: ^5.0`, `gedmo/doctrine-extensions: ^3.11`
- [x] 1.3 Remove leftover Acme skeleton files from `src/` (e.g. `AcmeSyliusExamplePlugin.php`)
- [x] 1.4 Create `src/SetonoSyliusQRCodePlugin.php` (final `Bundle` class)
- [x] 1.5 Update `tests/Application/config/bundles.php` to register the plugin bundle
- [x] 1.6 Update the test application kernel autoload map to the new namespace
- [ ] 1.7 Run `composer update` and `composer dump-autoload`; confirm `composer analyse` passes with no baseline

## 2. Configuration and Extension

- [x] 2.1 Create `src/DependencyInjection/Configuration.php` with keys: `redirect_type` (enum 301/302/307, default 307), `utm.source` (default `qr`), `utm.medium` (default `qrcode`), `logo.path` (nullable string), `logo.size` (int 0-100, default 60)
- [x] 2.2 Create `src/DependencyInjection/SetonoSyliusQRCodeExtension.php` (load + prepend: sylius_resource, sylius_grid for QR codes, sylius_grid extension for product bulk action)
- [x] 2.3 Create `src/Resources/config/services.xml` (XML per Symfony reusable-bundle best practice; acts as a dispatcher that `<imports>` per-folder files at `src/Resources/config/services/<folder>.xml` — one per root folder under `src/` that contains services). NO autowire/autoconfigure/resource auto-discovery — every service declared explicitly with FQCN id, class, arguments; interfaces registered as `<service ... alias="..."/>`
- [x] 2.4 Wire container parameters for config values (`setono_sylius_qr_code.*`)

## 3. Domain Model and Persistence

- [x] 3.1 Create `src/Model/QRCodeInterface.php` (getters/setters for all base fields — NO channel) and `QRCode.php` (concrete; no constructor logic beyond ArrayCollection init; nullable properties per CLAUDE.md conventions)
- [x] 3.2 Create `ProductRelatedQRCodeInterface` / `ProductRelatedQRCode` extending the base (adds `product` accessor)
- [x] 3.3 Create `TargetUrlQRCodeInterface` / `TargetUrlQRCode` extending the base (adds `targetUrl` accessor)
- [x] 3.4 Create `QRCodeScanInterface` / `QRCodeScan` with fields per spec (`scannedAt`, `ipAddress`, `userAgent`) — no `createdAt` (redundant with `scannedAt` in sync tracking)
- [x] 3.5 Create `src/Resources/config/doctrine/model/QRCode.orm.xml` as a `<mapped-superclass>` with `slug` marked `unique="true"`, Gedmo `<gedmo:timestampable on="create"/>` on `createdAt` and `on="update"` on `updatedAt`, and `<one-to-many>` to scans (no channel relation)
- [x] 3.6 Create `ProductRelatedQRCode.orm.xml` (mapped-superclass adding `product` many-to-one with `on-delete="CASCADE"`)
- [x] 3.7 Create `TargetUrlQRCode.orm.xml` (mapped-superclass adding `targetUrl` string 2048)
- [x] 3.8 Create `QRCodeScan.orm.xml` (mapped-superclass; Gedmo `<gedmo:timestampable on="create"/>` on `scannedAt`)
- [x] 3.9 Add app-level entities in `tests/Application/Entity/QRCode/` declaring STI `InheritanceType`, `DiscriminatorColumn('type')`, `DiscriminatorMap(['product' => ..., 'target_url' => ...])`
- [ ] 3.10 Document in README the STI discriminator configuration that adopting apps must provide, AND the required `stof_doctrine_extensions.orm.default.timestampable: true` configuration for Gedmo Timestampable

## 4. Sylius Resource Configuration

- [x] 4.1 Register resources via `AbstractResourceExtension::registerResources()` in the extension (driver + resources tree on the Configuration); bundle extends `AbstractResourceBundle`. Registers `qr_code` (base), `.product_related_qr_code`, `.target_url_qr_code`, `.qr_code_scan` with model/interface/controller/repository/factory classes
- [x] 4.2 Create `QRCodeRepositoryInterface` / `QRCodeRepository` with `findOneEnabledBySlug(string $slug): ?QRCodeInterface` and `getScansCount(QRCodeInterface): int` (plus `findOneBySlug` for validators)
- [x] 4.3 Create `QRCodeScanRepositoryInterface` / `QRCodeScanRepository` with time-range aggregation methods for the stats page
- [x] 4.4 Create `QRCodeFactoryInterface` / `QRCodeFactory` (two methods: `createProductRelated()`, `createTargetUrl()`) that seed defaults from the config — registered via `src/Resources/config/services/factory.xml`

## 5. Admin Grid and Routes

- [ ] 5.1 Prepend `sylius_grid.grids.setono_sylius_qr_code_admin_qr_code` per design (fields: name/slug/type/scansCount/enabled; filters: name/slug/type/enabled — NO channel column/filter; main dropdown for per-type create, row actions: stats/download/update/delete, bulk delete)
- [ ] 5.2 Create `src/Resources/views/admin/qr_code/grid/field/type.html.twig` rendering a label per discriminator value
- [ ] 5.3 Create `src/Resources/config/routes/admin.yaml` with the base resource (grid + delete + update) using a custom controller, plus two subtype resources (`only: ['create', 'update']`), plus stats / download / generate-name custom routes
- [ ] 5.4 Create `src/Controller/QRCodeController.php` extending Sylius `ResourceController` overriding `updateAction` to redirect to the correct subtype route based on the entity class
- [ ] 5.5 Prepend `sylius_grid.grids.sylius_admin_product.actions.bulk.generate_qr_codes` to add the bulk action to the product grid. NOTE: deferred until §12 — Sylius grid bulk actions require a template + working controller; the prepend alone 500s the product grid because Sylius looks for `@SyliusUi/Grid/BulkAction/<type>.html.twig` and there's no `default` template. This task is now bundled with §12.1–§12.3.

## 6. Admin Forms

- [x] 6.1 Create `Form/Type/ProductRelatedQRCodeType` with fields: `name`, `slug`, `product` (Sylius `ProductAutocompleteChoiceType`), `embedLogo`, `enabled`; advanced: `redirectType` (301/302/307), `utmSource`, `utmMedium`, `utmCampaign`, `errorCorrectionLevel` (Auto/L/M/Q/H). No channel field.
- [x] 6.2 Create `Form/Type/TargetUrlQRCodeType` with fields: `name`, `slug`, `targetUrl`, `embedLogo`, `enabled`; advanced identical to above. No channel field.
- [x] 6.3 Submit event listener (on the abstract `QRCodeType`) that resolves `errorCorrectionLevel = auto` to `H` or `M` based on `embedLogo` before the entity is persisted
- [x] 6.4 Abstract `QRCodeType` parent extending Sylius's `AbstractResourceType` holds the common fields
- [ ] 6.5 Custom `_form.html.twig` with advanced-settings accordion + `create.html.twig`/`update.html.twig` overrides — deferred; Sylius's default `@SyliusAdmin/Crud` templates render the form acceptably for now

## 7. Validation

- [x] 7.1 Create `src/Resources/config/validation/QRCode.xml` (name NotBlank + Length 255; slug NotBlank + Length 100 + Regex `^[a-z0-9-]+$`; errorCorrectionLevel Choice; class-level Symfony `UniqueEntity(fields="slug")`). Every constraint tagged with `<option name="groups"><value>setono_sylius_qr_code</value></option>` so it runs against the form's `validation_groups: ['setono_sylius_qr_code']`.
- [x] 7.2 Create `ProductRelatedQRCode.xml` (`product` NotNull, group tagged)
- [x] 7.3 Create `TargetUrlQRCode.xml` (`targetUrl` NotBlank + Url + Length 2048, group tagged)
- [x] 7.4 Slug uniqueness via Symfony's built-in `Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity` — no custom plugin constraint class required
- [x] 7.5 (superseded by 7.4 — no custom validator to test)
- [x] 7.6 Validation error messages live in `src/Resources/translations/validators.<locale>.yaml` (not `messages.*.yaml`) — Symfony's validator resolves its translations in the `validators` domain by default
- [x] 7.7 Forms SHALL never let the user bypass validation and reach a SQL constraint violation (see "Forms Enforce Symfony Validation Before Persistence" requirement in specs). Verified manually by submitting empty forms — Sylius re-renders with field-level errors instead of 500'ing.

## 8. AJAX Name Generation

- [ ] 8.1 Create `src/Action/Admin/GenerateNameAction.php` that reads `slug` or `productId` from the POST body, returns `{name: "QR: ..."}` as JSON, and is restricted to authenticated admins (route behind admin firewall)
- [ ] 8.2 Add client-side JS in `src/Resources/private/js/qr-code-name.js` that wires name auto-population on slug blur and product selection (only if the name field is empty)
- [ ] 8.3 Register the asset via Webpack Encore in the test application and reference from the form template

## 9. Redirect Action

- [ ] 9.1 Create `src/Resources/config/routes/frontend.yaml` with `setono_sylius_qr_code_redirect: /qr/{slug}` → `RedirectAction` (NOT locale-prefixed — imported separately from shop.yaml)
- [ ] 9.2 Create `src/Action/RedirectAction.php` implementing the flow: find QR by slug + enabled → 404 if missing; for `ProductRelatedQRCode` resolve channel via `ChannelContextInterface` and 404 if channel missing / product disabled / translation missing; call tracker (swallow exceptions and log); resolve target URL via `TargetUrlResolver`; return `RedirectResponse` with entity's `redirectType`. `TargetUrlQRCode` does NOT require a resolved request channel.
- [ ] 9.3 Create `TargetUrlResolverInterface` and `TargetUrlResolver` with two subtype branches: `TargetUrlQRCode` uses stored URL verbatim; `ProductRelatedQRCode` reads channel from `ChannelContextInterface` and builds the shop product URL using `channel.defaultLocale` + product translation slug. Include `appendUtmParameters()` helper (merge + override + skip-null)
- [ ] 9.4 Unit-test `TargetUrlResolver` with Prophecy: target-url subtype, product subtype on matching channel, product-with-missing-translation, UTM merge (no existing query / conflicting query / null fields)
- [ ] 9.5 Functional-test `RedirectAction`: success redirect for both subtypes, unknown slug, disabled QR, disabled product → 404, unknown hostname → 404 for product QR but success for target-URL QR, tracker exception does not block redirect

## 10. Scan Tracking

- [ ] 10.1 Create `src/Tracker/ScanTrackerInterface.php` and `ScanTracker.php` that builds a `QRCodeScan`, persists + flushes
- [ ] 10.2 Unit-test `ScanTracker` with Prophecy (verify entity fields populated, user-agent truncation, empty UA, missing IP)

## 11. Image Generation and Download

- [ ] 11.1 Create `src/Generator/QRCodeGeneratorInterface.php` and `QRCodeGenerator.php` using endroid/qr-code with configured defaults (black/white, margin 10, default size 1200 for PNG). Methods take `(QRCodeInterface $qrCode, ChannelInterface $channel, ...)` — the channel determines the encoded hostname.
- [ ] 11.2 In the generator, encode the absolute redirect URL computed from the supplied channel's hostname + slug
- [ ] 11.3 Apply entity `errorCorrectionLevel` to the builder; when `embedLogo = true` and a valid logo file exists, embed at configured `%` size; on missing logo file, log a warning and produce QR without logo
- [ ] 11.4 Create `src/Channel/DefaultChannelResolverInterface.php` and a default implementation `DefaultChannelResolver` that returns the first enabled channel (by code ascending) via `ChannelRepositoryInterface`, throwing a clear exception when none exist
- [ ] 11.5 Unit-test the generator with Prophecy: PNG size, error correction level forwarded, logo-absent fallback, encoded URL uses supplied channel's hostname, different channels → different encoded URLs
- [ ] 11.6 Unit-test `DefaultChannelResolver` with Prophecy: returns first enabled by code, skips disabled, throws when no enabled channels, substitution works when overridden
- [ ] 11.7 Create `src/Action/Admin/DownloadAction.php` handling `{format}` ∈ {png, svg, pdf} (requirements + default PNG) and optional `{channel}` path segment. When `channel` omitted → `DefaultChannelResolver`; when supplied → look up via `ChannelRepositoryInterface`, 404 if not found or disabled. Stream response with correct `Content-Type`, `Content-Disposition` (`<slug>.<ext>` for default, `<slug>-<channelCode>.<ext>` for explicit), `ETag` (hash of `id|updatedAt|format|channelCode`), `Cache-Control: private, max-age=86400`; honor `If-None-Match` → 304
- [ ] 11.8 Functional-test the download action: 3 formats, default (resolver-picked) channel, explicit channel, 304 on matching ETag, different ETag per channel, 200 + new ETag after `updatedAt` bump, 404 on unknown format, 404 on unknown channel code

## 12. Bulk Generation from Product Grid

- [ ] 12.1 Create `src/Action/Admin/BulkGenerateAction.php` that accepts selected product IDs + modal options (embedLogo, enabled — NO channel), iterates products, uses `$product->getSlug()` as the QR slug, skips products whose slug is already taken, persists valid QR codes in a single flush, and sets a flash message `Created X. Skipped Y (slug already exists).`
- [ ] 12.2 Add route `setono_sylius_qr_code_admin_bulk_generate` in `admin.yaml`
- [ ] 12.3 Create `_bulkGenerateModal.html.twig` (no channel picker) and wire it into the product grid via a template override / JS opener
- [ ] 12.4 Functional-test: 5 products all-new (5 created, 0 skipped), 3 products with 1 slug conflict (2 created, 1 skipped), zero-selection guard

## 13. Statistics Page

- [ ] 13.1 Create `src/Action/Admin/StatsAction.php` rendering `stats.html.twig` with totals, quick stats (7/30), pre-computed initial data for the default range, and a per-channel download matrix (default + one button per enabled channel, per format)
- [ ] 13.2 Create `StatsDataAction` (AJAX) returning JSON for chart refresh on range change: line data (daily for ≤30d, weekly for >30d, UTC-bucketed)
- [ ] 13.3 Create `ExportCsvAction` streaming CSV of scans for the selected range
- [ ] 13.4 Create `stats.html.twig` wiring Chart.js for the line chart, the scan table, and rendering the per-channel download matrix
- [ ] 13.5 Add repository aggregation queries on `QRCodeScanRepository` for: total, count since N days ago, daily buckets, weekly buckets, paginated recent scans within range
- [ ] 13.6 Integration-test the aggregation queries against a real DB fixture in `tests/Integration/Repository/QRCodeScanRepositoryTest.php`

## 14. Menu

- [x] 14.1 Create `src/Menu/AdminMenuListener.php` adding the `qr_codes` child under `marketing` with route and `qrcode` icon
- [x] 14.2 Register the listener as an event subscriber on `sylius.menu.admin.main` in `src/Resources/config/services/menu.xml` (imported from `services.xml`)
- [x] 14.3 Verify the menu entry appears in the test application after login

## 15. Translations

- [x] 15.1 Populate `src/Resources/translations/messages.en.yaml` with all UI keys from the inspiration doc §13 under `setono_sylius_qr_code.ui.*` and `setono_sylius_qr_code.form.*`
- [x] 15.2 Copy the file for each of the nine other locales (da, de, es, fr, it, nl, no, pl, sv) with English fallback values (human translation can follow in a separate PR)
- [x] 15.3 Populate `flashes.en.yaml` + the nine locale copies with the bulk-generate and error flash keys

## 16. Test Application Glue

- [ ] 16.1 Ensure the test app Kernel loads the plugin bundle and imports its routes (admin prefix `/admin`, frontend root)
- [ ] 16.2 Run `bin/console doctrine:schema:update --complete --force` in the test app; confirm both tables exist with the composite unique index
- [ ] 16.3 Add a basic fixture creating a channel, two products, one target-URL QR code, one product QR code, and a handful of scans for manual testing
- [ ] 16.4 Boot the test app locally, sign in (sylius/sylius), walk through: create of both subtypes, bulk generate from product grid, redirect hits, stats page, download in three formats, CSV export

## 17. Comprehensive Test Coverage

All tests MUST follow the project conventions (see `CLAUDE.md`): BDD-style method names (`it_should_...`), Prophecy via `ProphecyTrait` and `$this->prophesize()` (never `createMock`), and Symfony `TypeTestCase` for form tests.

### 17.1 Unit tests

- [ ] 17.1.1 `tests/Unit/DependencyInjection/ConfigurationTest.php` — default values, allowed `redirect_type` values (301/302/307), `logo.size` bounds (0-100), invalid config rejection
- [ ] 17.1.2 `tests/Unit/DependencyInjection/SetonoSyliusQRCodeExtensionTest.php` — resource prepend, grid prepend (both QR grid and product grid bulk action), service registration
- [ ] 17.1.3 `tests/Unit/Model/QRCodeTest.php` — base entity defaults in constructor, getters/setters, scan count accessor
- [ ] 17.1.4 `tests/Unit/Model/ProductRelatedQRCodeTest.php` and `TargetUrlQRCodeTest.php` — subtype-specific field behavior
- [ ] 17.1.5 `tests/Unit/Model/QRCodeScanTest.php` — field accessors, user-agent truncation; timestamps verified via an integration test (Gedmo listener only runs at flush time)
- [ ] 17.1.6 `tests/Unit/Factory/QRCodeFactoryTest.php` — both factory methods seed defaults from injected config; `utmCampaign` snapshot-from-slug behavior
- [x] 17.1.7 `tests/Unit/Validator/Constraints/UniqueSlugValidatorTest.php` — happy path, duplicate rejection, update-with-unchanged-slug allowed
- [ ] 17.1.8 `tests/Unit/Resolver/TargetUrlResolverTest.php` — target-url subtype (channel irrelevant), product subtype with matching request channel, product-with-missing-translation → exception, UTM append without existing query, UTM override of conflicting query, null UTM fields skipped, unknown subtype throws `LogicException`
- [ ] 17.1.9 `tests/Unit/Generator/QRCodeGeneratorTest.php` — PNG default size 1200 and custom size, error correction forwarded, logo embed when file exists, warning-and-fallback when logo path missing, encoded URL matches supplied channel's hostname + slug, same QR + two channels → two different encoded URLs
- [ ] 17.1.9b `tests/Unit/Channel/DefaultChannelResolverTest.php` — returns first enabled by code ascending, skips disabled, throws when none enabled
- [ ] 17.1.10 `tests/Unit/Tracker/ScanTrackerTest.php` — entity fields populated correctly, user-agent truncated at 512, empty UA stored as empty string, missing IP stored as `unknown`, persist + flush invoked
- [ ] 17.1.11 `tests/Unit/Controller/QRCodeControllerTest.php` — `updateAction` redirects to product subtype route for product QR, target-url subtype route for URL QR, throws for unknown type
- [x] 17.1.12 `tests/Unit/Menu/AdminMenuListenerTest.php` — adds `qr_codes` under `marketing`, no-op when marketing child absent

### 17.2 Form tests (Symfony `TypeTestCase`)

- [ ] 17.2.1 `tests/Unit/Form/Type/ProductRelatedQRCodeTypeTest.php` — submit valid data maps to entity, invalid submissions fail, `errorCorrectionLevel = Auto` resolves to `H` when `embedLogo = true` and `M` when false
- [ ] 17.2.2 `tests/Unit/Form/Type/TargetUrlQRCodeTypeTest.php` — same coverage shape, plus URL field validation round-trip
- [ ] 17.2.3 Shared advanced-settings field group covered once; subtype tests assert only their additions

### 17.3 Integration tests (real DB)

- [ ] 17.3.1 `tests/Integration/Repository/QRCodeRepositoryTest.php` — `findOneEnabledBySlug`: finds existing, misses unknown, misses when disabled; `getScansCount` accuracy; slug unique constraint trips on duplicate
- [ ] 17.3.2 `tests/Integration/Repository/QRCodeScanRepositoryTest.php` — total count, last-N-days count, daily buckets, weekly buckets, paginated recent scans
- [ ] 17.3.3 `tests/Integration/CascadeDeleteTest.php` — deleting a product removes its `ProductRelatedQRCode` and the QR's `QRCodeScan` rows
- [ ] 17.3.4 `tests/Integration/Model/TimestampableTest.php` — persisting a QR code populates `createdAt`/`updatedAt` via Gedmo; updating an existing QR bumps `updatedAt` but leaves `createdAt`; persisting a scan populates `scannedAt`

### 17.4 Functional tests (test application, HTTP layer)

- [ ] 17.4.1 `tests/Functional/Action/RedirectActionTest.php` — success redirect for both subtypes with correct status + `Location` (with UTM), unknown slug → 404, disabled QR → 404, disabled product (product-QR) → 404, unknown hostname (product-QR) → 404, unknown hostname (target-URL QR) → success, tracker exception → still redirects (error is logged)
- [ ] 17.4.2 `tests/Functional/Admin/QRCodeCrudTest.php` — create product QR, create target-URL QR, update redirect routes to correct subtype form, delete removes entity, bulk delete
- [ ] 17.4.3 `tests/Functional/Admin/GenerateNameActionTest.php` — slug → `{name: "QR: <slug>"}`, productId → `{name: "QR: <product name>"}`, unauthenticated → 401/403
- [ ] 17.4.4 `tests/Functional/Admin/DownloadActionTest.php` — PNG/SVG/PDF each return correct `Content-Type` and `Content-Disposition` (default and per-channel filename forms), default format is PNG, default-channel path uses the resolver, explicit channel path uses that channel's hostname, unknown format → 404, unknown/disabled channel → 404, ETag set on response, different channels → different ETags, matching `If-None-Match` → 304, updated entity yields a new ETag
- [ ] 17.4.5 `tests/Functional/Admin/BulkGenerateActionTest.php` — 5 new products → 5 created / 0 skipped, 3 products with 1 slug conflict → 2 created / 1 skipped, zero-selection guard, flash message rendered
- [ ] 17.4.6 `tests/Functional/Admin/StatsActionTest.php` — renders for existing QR, 404 for unknown, range selector JSON endpoint returns daily buckets ≤30d and weekly buckets >30d
- [ ] 17.4.7 `tests/Functional/Admin/ExportCsvActionTest.php` — header + data rows, ascending by `scanned_at`, header-only when no scans in range
- [ ] 17.4.8 `tests/Functional/Admin/MenuTest.php` — `QR Codes` link present under `Marketing` after admin login

### 17.5 Coverage expectations

- [ ] 17.5.1 Every new public class SHALL have at least one matching test class (unit, form, integration, or functional as appropriate)
- [ ] 17.5.2 Every scenario listed in the spec files MUST correspond to at least one executable test
- [ ] 17.5.3 No `markTestIncomplete`/`markTestSkipped` in committed code without an accompanying issue link

## 18. Quality Gates

- [ ] 18.1 `composer check-style` passes; run `composer fix-style` for any drift
- [ ] 18.2 `composer analyse` passes at PHPStan level max with no baseline entries
- [ ] 18.3 `composer phpunit` runs green for all added unit, integration, and functional tests
- [ ] 18.4 Update the root `README.md` with: installation, bundle registration, app entity STI snippet, config reference, and the cascade-delete caveat
- [ ] 18.5 Replace the `TODO` in `CLAUDE.md` "Project Overview" with a 2-3 line description of the plugin
