## Context

Sylius 1.14 plugin targeting PHP 8.1+. Green-field: the repo currently contains only skeleton code (the `Acme\SyliusExamplePlugin` scaffold from `setono/sylius-plugin-skeleton`). The user has renamed the namespace to `Setono\SyliusQRCodePlugin` and made `1.14.x` the default branch. An inspiration spec document exists at `docs/SyliusQRCodePlugin-Specification.md` but several points in it are inconsistent or premature — this change document supersedes it.

The plugin provides:

- admin-managed QR codes pointing at Sylius products or arbitrary URLs,
- a public `/qr/{slug}` redirect endpoint that records scans,
- on-demand image rendering (PNG/SVG/PDF),
- per-QR-code scan statistics in the admin.

External constraints that shape the design:

- Sylius Resource Bundle doesn't allow `abstract` base classes for resources, so STI bases must be concrete but treated as abstract by convention.
- Sylius channel resolution is hostname-based; the request context is the source of truth at redirect time.
- Coding standards (from `CLAUDE.md`): PHPStan max, ECS, PHPUnit with Prophecy (never `createMock`), BDD-style test names, Symfony `TypeTestCase` for form tests.
- Doctrine entity timestamps (`createdAt` / `updatedAt` / `scannedAt`) are populated by the Gedmo Timestampable extension, not by constructor logic or lifecycle callbacks. `stof/doctrine-extensions-bundle` + `timestampable: true` is required in the host application (documented in README).

## Goals / Non-Goals

**Goals:**

- A clean, testable Sylius resource model supporting future QR code types (WiFi, vCard, SMS, etc.) via STI.
- A channel-agnostic QR code model with per-channel image variants at download time, and request-channel-aware redirection for `ProductRelatedQRCode`.
- Synchronous scan tracking with an interface shape that allows later swap to async (Messenger) without breaking callers.
- On-demand image generation with HTTP caching — no filesystem state.
- Admin UX consistent with Sylius 1.14 conventions (grid + resource routes + resource templates).
- All new code under PHPStan level max and unit-tested with Prophecy.

**Non-Goals:**

- Pre-generating and storing images on Flysystem.
- Asynchronous scan tracking (can be added later behind the same interface).
- REST / API Platform endpoints for QR codes.
- Per-QR logo upload, custom colors, scheduling, expiration, tags, folders, roles (see Appendix B of the inspiration doc — explicitly out of scope for v1).
- Geolocation tracking of scans.

## Decisions

### 1. Single Table Inheritance for QR code types

The base `QRCode` class is persisted to `setono_sylius_qr_code__qr_code` with a `type` discriminator column. Subclasses `ProductRelatedQRCode` (`type = product`) and `TargetUrlQRCode` (`type = target_url`) add their type-specific columns.

- Each subclass is its own Sylius resource with its own form type and create/update routes.
- The base resource owns the index grid, delete action, and an `update` action that redirects to the correct subtype update route.
- The base class is concrete for Sylius Resource Bundle compatibility but should never be instantiated directly; subclass constructors are the only call sites.

**Alternatives considered:** single entity with a `type` enum and nullable columns. Rejected — new types (WiFi, vCard, …) would pile nullable columns and subtype-specific validation would live in controllers.

### 2. Global slug uniqueness; QR codes are channel-agnostic

Unique constraint is a single-column unique on `slug`. QR codes carry no FK to a channel. A `UniqueSlug` custom validator provides a human-readable form error before the DB constraint trips.

Lookup at redirect time: `QRCodeRepository::findOneEnabledBySlug($slug)`.

Channel still matters at two moments:

- **Redirect**, for `ProductRelatedQRCode` URL generation — resolved from the request via Sylius `ChannelContextInterface` (see §10).
- **Image generation**, for the hostname encoded into the QR image — supplied by the caller when known, or pulled from `DefaultChannelResolverInterface` otherwise (see §14).

**Alternatives considered:** per-channel slug uniqueness (composite index). Rejected — adds complexity for the common single-channel shop, and multi-channel per-slug routing is niche enough to live as an extension point (subclass `TargetUrlResolver` or the redirect action).

### 3. Synchronous scan tracking with an interface-first seam

`RedirectAction` depends on `ScanTrackerInterface::track(QRCodeInterface $qrCode, Request $request): void`. The v1 implementation inserts a `QRCodeScan` row (capturing IP and user agent only) and flushes inline. A future async variant (Symfony Messenger) can replace the implementation without changing callers. Device-type classification was considered and deliberately dropped to avoid pulling in `matomo/device-detector` — the raw user agent is still stored and can be analyzed later if needed.

`QRCodeRepository::getScansCount(QRCodeInterface $qrCode): int` is read off the grid aggregate query (not the entity) to avoid N+1 on the grid.

**Alternatives considered:** Messenger from day one. Rejected — it adds infrastructure (transports, worker) and the user explicitly accepted synchronous behavior for v1.

### 4. On-demand image generation, no storage

The admin download endpoint (`/admin/qr-codes/{id}/download/{format}`) generates the requested image at request time using `endroid/qr-code` and streams it with `Cache-Control` + `ETag` headers computed from `(qrCode.id, qrCode.updatedAt, format)`.

- No Flysystem dependency.
- No filesystem writes.
- Global logo config changes take effect immediately on the next request (no regeneration step).

**Alternatives considered:** pre-generating all three formats to Flysystem on create/update plus a regenerate-all command for logo config changes. Rejected — extra moving parts (console command, Flysystem config, storage cleanup on delete), little benefit for admin-only download traffic.

### 5. Error correction level: stored as L/M/Q/H, UI adds "Auto"

Database stores one of `L`, `M`, `Q`, `H`. The admin form offers `Auto` as an additional option; when submitted, `Auto` resolves to `H` if `embedLogo = true`, otherwise `M`. This happens in the form data mapper / entity factory, not at generation time, so the stored value is deterministic.

### 6. UTM parameter semantics

- `utmSource` (default `"qr"`), `utmMedium` (default `"qrcode"`), `utmCampaign` (default = slug) are snapshotted on the entity at creation time.
- Slug changes do not update `utmCampaign`. Admins can edit `utmCampaign` freely.
- The resolver merges UTM into the target URL's existing query string: if a UTM key is already present in the target URL, the QR's value overrides it.
- If a UTM field on the entity is `null`, no parameter is added for that key.

### 7. Config defaults vs entity values

Plugin YAML config:

```yaml
setono_sylius_qr_code:
    redirect_type: 307        # default for new entities; allowed: 301, 302, 307
    utm:
        source: qr
        medium: qrcode
    logo:
        path: null
        size: 60
```

These values are injected into an entity factory (`QRCodeFactory`) that sets defaults on newly created `ProductRelatedQRCode` / `TargetUrlQRCode` instances. At redirect and generation time, only the entity fields are read. Changing the YAML after QR codes have been created does not retroactively update them (except logo — see below).

Logo config is read at generation time, not stored on the entity. `embedLogo = true` means "embed whatever logo is currently configured globally"; if the config path changes, the next render uses the new logo.

### 8. Product deletion cascades to QR codes and scans

`ProductRelatedQRCode.product` FK uses `on-delete="CASCADE"`. QR codes of deleted products are removed, which also cascades to `QRCodeScan` rows via the QR → scan FK. Documented in the README.

**Trade-off accepted:** scan history is lost when a product is deleted. Admins who care must export CSV first. Simpler than soft-delete or 410 handling for v1.

### 9. Redirect status codes

Only `301`, `302`, `307` are offered. Default is `307` (temporary, method-preserving). `301`/`308` are excluded or narrowed for v1: `308` would be rare in QR usage, `303` is method-altering and surprising. Admins who need other codes can fork the choice list later.

### 10. Channel resolution at redirect time

The QR entity has no channel. The redirect action looks up the QR by slug only. For `ProductRelatedQRCode`, the `TargetUrlResolver` needs a channel to build an absolute product URL with the right default locale — it obtains it from the request via Sylius's `ChannelContextInterface` (hostname-based by default).

- If `ChannelContextInterface::getChannel()` throws or the resolved channel has no product translation for its default locale, the action returns 404. No fallback to a "default" channel at redirect time — scans on an unrecognized host should not silently point somewhere wrong.
- If the product is disabled on the resolved channel, the action returns 404 (same reasoning — don't expose a bad destination).
- `TargetUrlQRCode` ignores the request's channel entirely; the stored URL is authoritative.

Admins who want channel-specific routing on the QR itself (e.g. `summer-sale` should always redirect to the EU store regardless of scan hostname) subclass `TargetUrlResolver` or override `RedirectAction`.

### 11. Scan tracking data model

`QRCodeScan` columns: `id`, `qrCode` (FK), `scannedAt`, `ipAddress` (string 45 for IPv6), `userAgent` (string 512; truncate longer input).

No `createdAt` column — the row IS created at scan time in the synchronous v1 tracker, so `scannedAt` and a hypothetical `createdAt` would always be identical. If async tracking is added later, a `createdAt` can be introduced at that point.

No device-type column. Raw user agent is stored; any client-side segmentation happens in the stats UI if ever needed. This keeps the write path minimal and avoids a heavy dependency.

### 12. Name generation (AJAX)

`POST /admin/qr-codes/generate-name` accepts `{slug}` or `{productId}` and returns `{name}`. Used by the admin forms to populate the name field on slug blur or product selection. The name field remains user-editable after auto-population (populate only if empty).

### 13. Bulk product QR generation

A bulk action on the `sylius_admin_product` grid opens a Sylius-style modal with logo + enabled options (no channel picker — QR codes are global). For each selected product the action creates one `ProductRelatedQRCode` with `slug = $product->getSlug()` (the product's base slug, not a translation-specific one), skipping any product whose slug is already taken by another QR. The user sees a summary flash: `Created X. Skipped Y (slug already exists).`

If an adopting app prefers a locale-specific product slug for its QR slugs, they override the slug-derivation callable (documented extension point).

### 14. Default channel resolver & per-channel image downloads

The QR image encodes an absolute URL like `https://<hostname>/qr/<slug>`. Since the QR carries no channel, *which* hostname gets encoded becomes a decision point at generation time.

Two entry points:

- **Admin explicitly picks a channel** — the download endpoint accepts an optional channel code, `GET /admin/qr-codes/{id}/download/{format}[/{channel}]`. When the channel segment is present, the generator encodes that channel's hostname. The stats page exposes a download button per channel so admins can print artifacts for each storefront.
- **Admin downloads the "default" QR** — when no channel is provided, the generator calls `DefaultChannelResolverInterface::resolve(): ChannelInterface` to decide. The plugin ships a default implementation (first enabled channel from `ChannelRepositoryInterface::findBy(['enabled' => true])`, ordered deterministically). Adopting apps override by binding their own implementation to the interface.

Trade-offs:

- The resolver is called only when no channel is specified — if admins consistently pick a channel (explicit download links from the stats page), the resolver never runs.
- The generated image is regenerated on every download (on-demand generation, §4), so hostname changes in config propagate immediately — no cache invalidation needed beyond ETag on `(qrCode.id, updatedAt, format, channelCode)`.
- The ETag must include the channel segment so different channels of the same QR don't share a cache entry.

## Risks / Trade-offs

- **Scan write on hot path** → Every redirect performs a DB insert + flush. Mitigation: the `ScanTrackerInterface` seam lets us move to async (Messenger) without touching `RedirectAction`.
- **Cascade delete loses scan history** → Documented, not guarded. Mitigation: CSV export exists on the stats page; admins can back up before deletion.
- **On-demand PDF generation latency** → PDFs are the slowest format to generate. Mitigation: HTTP `Cache-Control` with a long `max-age` + ETag means browser-side reuse; regenerate only when entity changes. Admin download is rare enough to tolerate cold generation.
- **STI discriminator configured in app entity, not plugin mapping** → The plugin ships a `<mapped-superclass>` for the base; the concrete app entity adds the `InheritanceType`/`DiscriminatorColumn`/`DiscriminatorMap`. This is standard Sylius pattern but non-obvious. Mitigation: documented in README with a worked example.
- **No channel on the QR — multi-channel per-slug routing is not supported out of the box** → Admins with a hard requirement that `summer-sale` always routes to the EU store regardless of scan hostname must override `TargetUrlResolver` / `RedirectAction`. Mitigation: documented as the v1 extension point; the interface seams are explicit.
- **Default channel resolver is a single point of "ambient" behavior** → The resolver runs when admins download without picking a channel, and the default implementation's "first enabled channel" rule may not match what an operator intuitively considers default. Mitigation: it's an interface — apps override freely; the default is documented.
- **Slug regex `^[a-z0-9-]+$` rejects Unicode** → Intentional for clean URLs. Mitigation: name field (which admins see) supports full UTF-8.
- **`embedLogo = true` with no global logo configured** → Could silently produce a QR without a logo. Mitigation: `QRCodeGenerator` logs a warning if `embedLogo = true` but logo path is null/missing; admin forms validate that logo is configured before allowing `embedLogo = true`.
- **`unique(channel, slug)` at DB level + validator** → Two enforcement points could diverge. Mitigation: validator uses the repository query; DB constraint is the final safety net. Acceptable duplication for UX.

## Migration Plan

This is a new plugin — no data migration. Deployment steps for adopting applications:

1. `composer require setono/sylius-qr-code-plugin`
2. Register the bundle in `config/bundles.php`.
3. Create app-level entities extending the mapped superclasses and declaring the STI discriminator map (README example).
4. `php bin/console doctrine:migrations:diff` + review + `migrate`.
5. Optionally configure `setono_sylius_qr_code.logo.path` and other defaults.
6. Grant admin permissions for the new `setono_sylius_qr_code_admin_qr_code_*` routes.

Rollback: drop the two tables and remove the bundle. No shared-table changes.

## Open Questions

- Should the stats page honor admin timezone when bucketing scans, or always UTC? Default to UTC for v1; revisit if admin feedback requests otherwise.
- Should `QRCodeScan.ipAddress` be anonymized (e.g., last octet zeroed) for GDPR? Out of scope for v1 — admins with GDPR requirements can subclass the tracker. Note it in README.
- Do we want a "hit this QR code" admin action for previewing redirects without incrementing scan count? Nice-to-have; postpone.
