## Why

Sylius merchants need a way to generate, distribute, and measure QR codes (packaging, print, in-store signage, campaigns) without leaving the admin panel. Third-party QR tools require copy-pasting URLs, provide no scan analytics tied to the catalog, and cannot be channel-aware. A native plugin lets admins point QRs directly at Sylius products or any URL, tracks scans per channel, and keeps the operational surface inside Sylius.

## What Changes

- Add a new Sylius resource hierarchy `QRCode` with Single Table Inheritance: base `QRCode`, subclasses `ProductRelatedQRCode` and `TargetUrlQRCode` (more types expected later: WiFi, vCard, etc.).
- Add a public redirect endpoint `/qr/{slug}` that looks up the QR code by slug (globally unique), synchronously records a scan, and redirects to the resolved target URL with UTM parameters appended. For `ProductRelatedQRCode`, the target URL is computed against the **request's current channel** (resolved via Sylius `ChannelContextInterface`) — no channel is stored on the QR.
- Add admin CRUD for QR codes (grid, forms per subtype, two-option "Create" dropdown, delete, update).
- Add a statistics page per QR code (scans over time line chart, paginated scan table, CSV export).
- Add a bulk-generate action on the Sylius product grid that creates one `ProductRelatedQRCode` per selected product, keyed by the product slug.
- Add on-demand image generation for PNG, SVG, and PDF formats, streamed from a download endpoint with HTTP cache headers. No pre-generation, no disk storage. The download endpoint accepts an optional channel code so admins can download one QR image per channel (each encoding that channel's hostname); when omitted, the image is generated for the channel returned by a `DefaultChannelResolverInterface`.
- Add plugin-level configuration for global logo embedding (optional path + size) and default redirect HTTP status code (default 307; allowed values 301, 302, 307).
- Add scan tracking capturing IP and user agent only (no device-type classification).
- Slug uniqueness is enforced **globally** (unique on `slug`). QR codes are channel-agnostic; per-channel routing is an extension point (subclass `TargetUrlResolver` or the redirect action).
- Product deletion **cascades** to QR codes and their scans (documented behavior).
- `utm_campaign` is **snapshot at create time** from the slug; later slug changes do not propagate.
- Error correction level exposes an "Auto" UI option that resolves at save to `M` (no logo) or `H` (logo embedded); stored value is always one of `L`, `M`, `Q`, `H`.
- Config-defined `redirect_type` seeds new entities; the entity's own `redirectType` is always the source of truth at redirect time.

## Capabilities

### New Capabilities

- `qr-code-management`: Sylius resource model, admin CRUD (grid + forms per subtype), validation (global slug uniqueness, product/URL requirements), and bulk generation from the product grid.
- `qr-code-redirect`: Public `/qr/{slug}` endpoint, target URL resolution per subtype, UTM parameter snapshotting and appending, configurable HTTP redirect status. `ProductRelatedQRCode` URL resolution uses the request's current Sylius channel.
- `qr-code-generation`: On-demand PNG/SVG/PDF image rendering, error-correction auto-selection, optional global logo embedding, admin download endpoint with optional per-channel variants and HTTP cache headers. Ships a `DefaultChannelResolverInterface` service used when the caller does not specify a channel.
- `qr-code-scan-tracking`: Synchronous per-scan recording (ip, user agent, timestamp), statistics page (time-range line chart, paginated recent scans, CSV export).

### Modified Capabilities

<!-- No existing capabilities — this is a green-field plugin. -->

## Impact

- **New code**: `src/Action`, `src/Action/Admin`, `src/Controller`, `src/Form/Type`, `src/Generator`, `src/Menu`, `src/Model`, `src/Repository`, `src/Resolver`, `src/Tracker`, plus `src/DependencyInjection/Configuration.php` and the bundle extension.
- **New resources**: `src/Resources/config/{routes,doctrine/model,validation}/`, admin Twig templates, grid/resource YAML via bundle `prepend()`, translations across ten locales.
- **Composer deps added**: `endroid/qr-code ^5.0`, `gedmo/doctrine-extensions ^3.11` (for automatic `createdAt`/`updatedAt` population via the Timestampable listener). No Flysystem, no device detector.
- **Composer deps removed / skeleton cleanup**: Acme skeleton code replaced; `composer.json` name/description/autoload updated (already renamed by user).
- **Database schema**: new tables `setono_sylius_qr_code__qr_code` (STI, discriminator `type`) and `setono_sylius_qr_code__qr_code_scan`; unique index on `slug`.
- **Admin UX**: new menu entry under Marketing; new "Generate QR codes" bulk action on `sylius_admin_product` grid.
- **Public surface**: new route `setono_sylius_qr_code_redirect` on `/qr/{slug}`.
- **Performance**: every scan performs one DB insert on the hot path. Acceptable for v1; interface shape leaves room for async dispatch later.
- **Breaking changes**: none — new plugin.
