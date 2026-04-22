## ADDED Requirements

### Requirement: Public Redirect Endpoint

The plugin SHALL expose a public HTTP GET route `/qr/{slug}` (name `setono_sylius_qr_code_redirect`). The endpoint SHALL look up the QR code by its globally unique `slug` and return an HTTP redirect to the resolved target URL. The HTTP status code of the redirect SHALL equal the entity's `redirectType` (one of `301`, `302`, `307`). QR codes are channel-agnostic; the request's current Sylius channel is consulted only when computing a `ProductRelatedQRCode`'s target URL.

#### Scenario: Successful redirect to a target URL

- **WHEN** an enabled `TargetUrlQRCode` exists with slug `summer-sale` and `targetUrl = https://example.com/sale`, and a client requests `/qr/summer-sale` on any channel's hostname
- **THEN** the response is an HTTP redirect with status equal to the entity's `redirectType` and the `Location` header is `https://example.com/sale` with UTM parameters appended

#### Scenario: Unknown slug returns 404

- **WHEN** a client requests `/qr/does-not-exist`
- **THEN** the response status is 404

#### Scenario: Disabled QR code returns 404

- **WHEN** a QR code exists with `enabled = false`
- **THEN** requests to `/qr/{slug}` return 404

### Requirement: Target URL Resolution by Subtype

The plugin SHALL include a `TargetUrlResolverInterface` that computes the redirect URL from the QR code entity and the current request:

- For `TargetUrlQRCode`, use the `targetUrl` property verbatim (before UTM merging). The request's channel is not consulted.
- For `ProductRelatedQRCode`, resolve the current channel via `ChannelContextInterface`, then generate the URL for the `sylius_shop_product_show` route using the channel's default-locale product translation slug and absolute URL generation.
- Unknown subtypes SHALL cause the resolver to throw a `LogicException`.

#### Scenario: Target URL QR resolves to its stored URL regardless of channel

- **WHEN** the resolver is given a `TargetUrlQRCode` with `targetUrl = https://example.com/page` and any request
- **THEN** the pre-UTM result is `https://example.com/page`

#### Scenario: Product QR resolves to the shop product URL on the request's channel

- **WHEN** the resolver is given a `ProductRelatedQRCode` whose product has translation slug `summer-t-shirt` for channel A's default locale `en_US`, and the request resolves (via `ChannelContextInterface`) to channel A
- **THEN** the pre-UTM result is the absolute URL of `sylius_shop_product_show` with `{slug: summer-t-shirt, _locale: en_US}` on channel A's hostname

### Requirement: Product QR Codes Require a Valid Request Channel

When resolving a `ProductRelatedQRCode`, if `ChannelContextInterface::getChannel()` cannot produce a channel (e.g. unrecognized hostname), or the product is not enabled on the resolved channel, or the product has no translation for the channel's default locale, the redirect endpoint SHALL return 404 and SHALL NOT record a scan.

#### Scenario: Unknown hostname for a product QR returns 404

- **WHEN** a client requests `/qr/{slug}` of a `ProductRelatedQRCode` on a hostname that does not match any Sylius channel
- **THEN** the response status is 404 and no scan is recorded

#### Scenario: Disabled product on the resolved channel returns 404

- **WHEN** the resolved channel exists but the linked product is disabled (or not enabled for that channel)
- **THEN** the response status is 404 and no scan is recorded

#### Scenario: Target URL QR works on unknown hostname

- **WHEN** a client requests `/qr/{slug}` of a `TargetUrlQRCode` on a hostname that does not match any Sylius channel
- **THEN** the redirect succeeds (the stored URL is used) and a scan is recorded

### Requirement: UTM Parameter Appending and Snapshotting

The resolver SHALL merge entity UTM values into the target URL's query string. If a UTM key already exists in the target URL's query, the entity value SHALL override it. If an entity UTM field is `null`, no parameter SHALL be added for that key. `utmSource`, `utmMedium`, and `utmCampaign` are snapshotted on the entity at creation time (defaults: `qr`, `qrcode`, and the slug respectively) and SHALL NOT be updated automatically when the slug changes later.

#### Scenario: UTM parameters are appended to a target URL without existing query

- **WHEN** resolving a QR with `targetUrl = https://example.com/page`, `utmSource = qr`, `utmMedium = qrcode`, `utmCampaign = spring-2026`
- **THEN** the final URL is `https://example.com/page?utm_source=qr&utm_medium=qrcode&utm_campaign=spring-2026` (query parameter order need not be deterministic as long as all three are present)

#### Scenario: Entity UTM overrides existing UTM in target URL

- **WHEN** resolving a QR with `targetUrl = https://example.com/page?utm_source=email` and entity `utmSource = qr`
- **THEN** the final URL's `utm_source` query value is `qr`, not `email`

#### Scenario: Null UTM fields are not added

- **WHEN** resolving a QR with `utmSource = null`
- **THEN** the final URL does not contain a `utm_source` query parameter

### Requirement: Scan Is Recorded Before Redirect

On every successful redirect (QR found, enabled, resolution succeeded), the action SHALL call `ScanTrackerInterface::track()` before issuing the redirect. If the tracker throws, the action SHALL still return the redirect response (a failed scan record does not break the user's experience).

#### Scenario: Successful scan is recorded

- **WHEN** a client successfully scans a valid, enabled QR code
- **THEN** a new `QRCodeScan` row exists for that QR with `scannedAt` set to the current time

#### Scenario: Tracker failure does not block redirect

- **WHEN** the tracker throws an exception during `track()`
- **THEN** the client still receives the redirect response and the error is logged
