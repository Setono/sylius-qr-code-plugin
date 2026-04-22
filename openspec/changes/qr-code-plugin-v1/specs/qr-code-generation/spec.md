## ADDED Requirements

### Requirement: QR Code Generator Interface

The plugin SHALL provide a `QRCodeGeneratorInterface` with three methods returning binary image payloads: `generatePng(QRCodeInterface $qrCode, ChannelInterface $channel, int $size = 1200): string`, `generateSvg(QRCodeInterface $qrCode, ChannelInterface $channel): string`, and `generatePdf(QRCodeInterface $qrCode, ChannelInterface $channel): string`. The generator SHALL use the `endroid/qr-code` library. The encoded content SHALL be the public URL of the QR code's redirect route (`/qr/{slug}`) resolved against the given channel's hostname.

#### Scenario: Generator encodes the redirect URL on the given channel's hostname

- **WHEN** `generatePng()` is called for a QR code with slug `summer-sale` and channel A (hostname `a.example`)
- **THEN** the returned PNG payload encodes the URL `https://a.example/qr/summer-sale`

#### Scenario: Same QR, different channels, different encoded URLs

- **WHEN** `generatePng()` is called twice for the same QR code with channel A (hostname `a.example`) and channel B (hostname `b.example`)
- **THEN** the two images encode different URLs (`https://a.example/qr/<slug>` and `https://b.example/qr/<slug>` respectively) while sharing the same slug

#### Scenario: PNG size parameter is honored

- **WHEN** `generatePng()` is called with `size = 600`
- **THEN** the resulting PNG image has width and height of 600 pixels (within library tolerance)

### Requirement: Default Channel Resolver Service

The plugin SHALL provide a `DefaultChannelResolverInterface` with a single method `resolve(): ChannelInterface`. The interface SHALL be used whenever the generator or the download endpoint needs a channel but the caller did not specify one. The plugin SHALL ship a default implementation that returns the first enabled channel from `ChannelRepositoryInterface` ordered by a deterministic criterion (e.g. by code ascending), and SHALL throw a descriptive exception if no enabled channel exists. Adopting applications SHALL be able to override the default by binding their own implementation to `DefaultChannelResolverInterface` in the service container.

#### Scenario: Default resolver returns the first enabled channel

- **WHEN** two enabled channels exist with codes `eu` and `us`, and one disabled channel `old`
- **THEN** the default resolver returns the channel with code `eu` (first enabled by code ascending)

#### Scenario: No enabled channels raises a clear error

- **WHEN** no enabled channel exists in the database
- **THEN** the default resolver throws an exception whose message clearly indicates that no default channel could be resolved

#### Scenario: Overriding the resolver takes effect

- **WHEN** an application binds a custom class to `DefaultChannelResolverInterface` in `services.yaml`
- **THEN** the custom resolver is used by the generator and the download endpoint wherever the interface is consumed

### Requirement: Default Generation Settings

Generated images SHALL use black foreground (`#000000`), white background (`#FFFFFF`), and a margin of 10 units. The default PNG size SHALL be 1200 px when no size is specified.

#### Scenario: Default PNG is 1200 px

- **WHEN** `generatePng()` is called without a size argument
- **THEN** the PNG image is 1200 pixels square

### Requirement: Error Correction Level Sourced from Entity

The generator SHALL read `errorCorrectionLevel` from the QR code entity and pass the corresponding endroid level to the builder. The entity value is always one of `L`, `M`, `Q`, `H` (UI "Auto" resolution happens at save time — see `qr-code-management`).

#### Scenario: Entity error correction level is applied

- **WHEN** the generator receives an entity with `errorCorrectionLevel = Q`
- **THEN** the endroid builder is configured with the Q error correction level

### Requirement: Optional Global Logo Embedding

When `embedLogo = true` on the entity AND a valid logo path is configured at `setono_sylius_qr_code.logo.path`, the generator SHALL embed the logo centered on the QR code, scaled to `setono_sylius_qr_code.logo.size` percent of the QR code's width. When `embedLogo = true` but no logo path is configured (or the file does not exist), the generator SHALL log a warning and produce the QR code without a logo.

#### Scenario: Logo is embedded when configured

- **WHEN** `embedLogo = true` and the configured logo file exists
- **THEN** the generated PNG contains the logo at the configured relative size, centered

#### Scenario: Missing logo file falls back gracefully

- **WHEN** `embedLogo = true` but the configured logo path does not exist
- **THEN** a warning is logged and a QR code without a logo is returned (no exception thrown)

#### Scenario: embedLogo = false ignores global logo

- **WHEN** `embedLogo = false`
- **THEN** the generated image contains no logo regardless of configuration

### Requirement: Admin Download Endpoint with Per-Channel Variants

The plugin SHALL expose `GET /admin/qr-codes/{id}/download/{format}` and `GET /admin/qr-codes/{id}/download/{format}/{channel}` (name `setono_sylius_qr_code_admin_qr_code_download`), where `format` is one of `png`, `svg`, `pdf` (default `png`) and `channel` is an optional channel code. The endpoint SHALL generate the image on demand, stream it in the response body with the correct `Content-Type` header (`image/png`, `image/svg+xml`, `application/pdf`), and include a `Content-Disposition: attachment; filename="<slug>[-<channelCode>].<format>"` header (the channel code is appended only when the channel is not the default).

When `channel` is supplied, the generator encodes that channel's hostname. When `channel` is omitted, the endpoint SHALL resolve the channel via `DefaultChannelResolverInterface` and encode that channel's hostname. If the supplied `channel` code does not match any enabled channel, the response SHALL be 404.

#### Scenario: Download PNG for the default channel

- **WHEN** an admin requests `GET /admin/qr-codes/42/download/png` for a QR code with slug `summer-sale` and the default resolver returns channel `eu`
- **THEN** the response has `Content-Type: image/png`, `Content-Disposition: attachment; filename="summer-sale.png"`, and the body is a PNG encoding the `eu` hostname URL

#### Scenario: Download PNG for an explicit channel

- **WHEN** an admin requests `GET /admin/qr-codes/42/download/png/us` and channel `us` is enabled
- **THEN** the response has `Content-Disposition: attachment; filename="summer-sale-us.png"` and the body encodes the `us` hostname URL

#### Scenario: Default format is PNG

- **WHEN** an admin requests `GET /admin/qr-codes/42/download`
- **THEN** the response is a PNG download for the default channel

#### Scenario: Unknown format returns 404

- **WHEN** an admin requests `GET /admin/qr-codes/42/download/gif`
- **THEN** the response status is 404

#### Scenario: Unknown or disabled channel code returns 404

- **WHEN** an admin requests `GET /admin/qr-codes/42/download/png/nonexistent`
- **THEN** the response status is 404

### Requirement: Stats Page Offers a Download per Channel

The stats page (`setono_sylius_qr_code_admin_qr_code_stats`) SHALL present a download control per format AND per enabled channel, using the per-channel download endpoint. A default download (no channel segment) SHALL also be offered for each format.

#### Scenario: Multi-channel site shows a download button per channel

- **WHEN** the site has three enabled channels and an admin opens the stats page for a QR
- **THEN** the stats page renders, for each of PNG/SVG/PDF, a "Default" download button plus one button per enabled channel, each pointing to the corresponding per-channel download URL

#### Scenario: Single-channel site still shows a default download

- **WHEN** the site has exactly one enabled channel
- **THEN** the stats page renders a single default download button per format (no redundant per-channel buttons required)

### Requirement: Download Endpoint HTTP Cache Headers

The download endpoint SHALL set an `ETag` header computed from `(qrCode.id, qrCode.updatedAt, format, channelCode)` where `channelCode` is the code of the channel actually used (either the supplied channel or the one chosen by the default resolver). It SHALL also set `Cache-Control: private, max-age=86400`. If the request's `If-None-Match` header matches the computed ETag, the endpoint SHALL return `304 Not Modified` without regenerating the image.

#### Scenario: ETag is set on download response

- **WHEN** an admin downloads a PNG
- **THEN** the response contains an `ETag` header derived from the QR code's id, `updatedAt`, the format, and the channel code

#### Scenario: Different channels have different ETags

- **WHEN** an admin downloads the PNG for channel `us` and separately for channel `eu` (same QR, same format, same `updatedAt`)
- **THEN** the two responses return different `ETag` values

#### Scenario: Matching If-None-Match returns 304

- **WHEN** an admin repeats a request with an `If-None-Match` header equal to the previously returned ETag, and the QR code has not been updated and the same channel is used
- **THEN** the response status is 304 and the body is empty

#### Scenario: Updating the QR code invalidates the ETag

- **WHEN** a QR code is updated (bumping `updatedAt`) and the admin re-requests the download
- **THEN** the new response has a different `ETag` and a regenerated image body
