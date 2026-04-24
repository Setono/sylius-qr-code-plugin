## ADDED Requirements

### Requirement: Scan Event Dispatch

The redirect action (`Setono\SyliusQRCodePlugin\Controller\RedirectAction`) SHALL dispatch a `QRCodeScannedEvent` (carrying the resolved `QRCodeInterface` and the incoming `Request`) through Symfony's `EventDispatcherInterface` before returning the `RedirectResponse`. The event is the single extension point for scan-driven side effects — the plugin's own scan tracker SHALL subscribe to this event, and adopting applications SHALL be able to register their own listeners/subscribers for additional tracking (analytics pipelines, Matomo, Google Analytics server-side, Segment, Slack notifications, etc.) without forking or decorating the redirect action.

Listener exceptions SHALL NOT block the redirect — the redirect action catches Throwable from dispatch and logs at error level, so a misbehaving third-party listener cannot prevent the user from reaching the target URL.

#### Scenario: Plugin scan tracker reacts to the event

- **WHEN** an enabled QR code is resolved and `RedirectAction` dispatches the event
- **THEN** the plugin's built-in scan tracker subscriber fires and persists a `QRCodeScan` row for that QR code

#### Scenario: Third-party listener receives the event

- **WHEN** an adopting app registers an event listener on the `QRCodeScannedEvent` class
- **THEN** that listener receives the event with the scanned QR code and the originating request, and can run its own tracking side-effects

#### Scenario: Listener exception does not block the redirect

- **WHEN** a registered listener throws during `dispatch()`
- **THEN** the redirect action catches the exception, logs it at error level with the QR code id and slug, and still returns the configured `RedirectResponse`

### Requirement: Scan Tracker Subscriber

The plugin SHALL provide a scan tracker implementation registered as an event subscriber on `QRCodeScannedEvent`. The subscriber SHALL create and persist a `QRCodeScan` entity from the event's QR code + request. Adopting applications that want to substitute tracking (async via Symfony Messenger, a different store, no store at all) can either swap the service that implements the subscriber or disable the shipped one and register their own.

#### Scenario: Subscriber creates a scan record

- **WHEN** `QRCodeScannedEvent` is dispatched with a QR code and a request
- **THEN** a new `QRCodeScan` row exists in the database linked to that QR code

### Requirement: Scan Table Index Layout

The `setono_sylius_qr_code__qr_code_scan` table SHALL carry a composite B-tree index on `(qr_code_id, scanned_at)`. The leading column matches every per-QR query (count, range-count, range-bucket, recent-scans). The trailing column orders the remaining reads — the range scans in the stats page, and the `ORDER BY scanned_at DESC LIMIT ...` recent-scans query — so the database can serve them from the already-ordered index without a filesort.

The `setono_sylius_qr_code__qr_code` table SHALL carry an index on the `type` (STI discriminator) column for the admin grid's Type filter. The `slug` column is UNIQUE (which implies an index) and already covers `findOneBySlug` / `findOneEnabledBySlug` — the redirect endpoint's hot path.

#### Scenario: Scan queries use the composite index

- **WHEN** `EXPLAIN` is run on `SELECT ... FROM setono_sylius_qr_code__qr_code_scan WHERE qr_code_id = ? AND scanned_at >= ? AND scanned_at < ? ORDER BY scanned_at DESC`
- **THEN** the query plan references the `(qr_code_id, scanned_at)` composite index (key used) and does not list a filesort

### Requirement: Scan Record Fields

Each `QRCodeScan` record SHALL capture: a reference to the QR code, `scannedAt` (datetime immutable, UTC, populated by Gedmo Timestampable at flush), `ipAddress` (client IP; the literal string `unknown` if absent), and `userAgent` (truncated to 512 characters). Scans are immutable — there is no `updatedAt`. There is no separate `createdAt` — in the synchronous v1 tracker the row is created at scan time, so it would duplicate `scannedAt`. The plugin does not classify scans into device categories in v1; the raw user agent is retained for optional downstream analysis.

#### Scenario: Scan captures client IP and user agent

- **WHEN** a request arrives with `X-Forwarded-For` resolving to `203.0.113.5` and `User-Agent: Mozilla/5.0 (iPhone)`
- **THEN** the persisted scan has `ipAddress = 203.0.113.5` and `userAgent` beginning with `Mozilla/5.0 (iPhone)`

#### Scenario: User agent longer than 512 chars is truncated

- **WHEN** a request has a `User-Agent` header longer than 512 characters
- **THEN** the persisted `userAgent` is the first 512 characters

#### Scenario: Missing user agent stores "unknown"

- **WHEN** a request has no `User-Agent` header
- **THEN** the persisted `userAgent` is the string `unknown`

#### Scenario: Missing client IP stores "unknown"

- **WHEN** `$request->getClientIp()` returns null
- **THEN** the persisted `ipAddress` is the string `unknown`

### Requirement: Statistics Page

The plugin SHALL expose `GET /admin/qr-codes/{id}/stats` (name `setono_sylius_qr_code_admin_qr_code_stats`) rendering a statistics page for the given QR code. The page SHALL show: the QR code's name and a preview image, download buttons for PNG/SVG/PDF, total scan count, quick-stat cards for "Last 7 days" and "Last 30 days", a time-range selector with presets 7/30/90 days, a line chart of scans over time, and a paginated table of recent scans. Time buckets SHALL be daily for ranges of 30 days or fewer and weekly for longer ranges. All bucketing SHALL be in UTC for v1.

#### Scenario: Stats page renders for a known QR code

- **WHEN** an admin navigates to `/admin/qr-codes/42/stats` for an existing QR code
- **THEN** the page renders with totals, a line chart, and a scan table

#### Scenario: Stats page for unknown QR code returns 404

- **WHEN** an admin navigates to `/admin/qr-codes/9999/stats` with no such entity
- **THEN** the response status is 404

#### Scenario: Range selector updates chart

- **WHEN** an admin selects the "90 days" preset
- **THEN** the line chart data is fetched (via AJAX) for the last 90 days with weekly buckets

### Requirement: Scans Count Available on the Grid

The QR code admin grid SHALL display a `scansCount` column reflecting the total number of scans recorded for each QR code. The count SHALL be computed via a repository query that avoids per-row N+1 lookups.

#### Scenario: Grid shows correct scan count

- **WHEN** a QR code has 42 recorded scans and the admin opens the grid
- **THEN** the `scansCount` column for that row shows `42`

#### Scenario: Grid loads scan counts without N+1 queries

- **WHEN** the grid renders 25 QR code rows
- **THEN** scan counts for all 25 rows are retrieved in at most a single aggregate query (not one query per row)
