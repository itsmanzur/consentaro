# Consentaro Hooks Reference

This is a reference for theme and plugin developers who want to customize Consentaro's behavior from `functions.php` or a small mu-plugin, without editing Consentaro's own source. It covers every filter and action Consentaro fires — none of them require Consentaro's code to change to use.

> **Note on completeness:** this list was produced by grepping `apply_filters(`/`do_action(` across `src/` directly against the running code, so it should always match what's actually shippable. If you add or rename a hook, please update this file in the same change.

- [Filters](#filters)
- [Actions](#actions)

## Filters

### `consentaro_default_consent_states`

**File:** `src/Consent/ConsentMode.php`

Overrides the default Google Consent Mode v2 state (before any visitor decision) for all 7 consent categories. Used both for the `wp_head` default `gtag()` call and as the fallback whenever a visitor's actual choice can't be read.

| Parameter | Type | Description |
|---|---|---|
| `$states` | `array<string, string>` | Map of the 7 category keys (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, `functionality_storage`, `personalization_storage`, `security_storage`) to `'granted'` or `'denied'`. |

```php
// Also deny functionality_storage by default (Consentaro normally grants it).
add_filter( 'consentaro_default_consent_states', function ( array $states ): array {
	$states['functionality_storage'] = 'denied';
	return $states;
} );
```

### `consentaro_wait_for_update_ms`

**File:** `src/Consent/ConsentMode.php`

Milliseconds `gtag('consent', 'default', ...)` waits for a matching `update` call (i.e. a banner interaction) before Google Tag's own measurement falls back to the default (denied) state. Consentaro defaults to `500`.

| Parameter | Type | Description |
|---|---|---|
| `$wait_ms` | `int` | Wait time in milliseconds. Default `500`. |

```php
// Give slower-rendering pages more time before the default state locks in.
add_filter( 'consentaro_wait_for_update_ms', function ( int $wait_ms ): int {
	return 1500;
} );
```

### `consentaro_user_geo_country`

**File:** `src/Integration/GeoLocation.php`

Overrides the resolved two-letter visitor country code, short-circuiting Consentaro's own Cloudflare → server GeoIP → ip-api.com detection chain entirely. Mainly useful for testing or when you already have a reliable country source (e.g. a CDN header Consentaro doesn't know about).

| Parameter | Type | Description |
|---|---|---|
| `$country` | `string\|null` | Country code to use instead, or `null` to let Consentaro detect it normally. Default `null`. |

```php
// Force EU detection for every visitor on a staging site.
add_filter( 'consentaro_user_geo_country', function ( ?string $country ): string {
	return 'DE';
} );
```

### `consentaro_force_banner_display`

**File:** `src/Integration/GeoLocation.php`

Forces the consent banner to render regardless of geo targeting or an already-stored consent cookie. Overrides everything else in `shouldShowBanner()`.

| Parameter | Type | Description |
|---|---|---|
| `$force` | `bool` | Whether to force the banner to show. Default `false`. |

```php
// Always show the banner on a specific QA page, even with a stored choice.
add_filter( 'consentaro_force_banner_display', function ( bool $force ): bool {
	return isset( $_GET['consentaro_preview'] ) ? true : $force; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} );
```

### `consentaro_tracker_signatures`

**File:** `src/Integration/TrackerSignatures.php`

Extends or overrides the built-in domain/URL-fragment → category map used to auto-categorize third-party `<script src="...">` tags for script blocking.

| Parameter | Type | Description |
|---|---|---|
| `$map` | `array<string, string>` | Domain or path fragment (lowercase) => one of `necessary`, `analytics`, `marketing`, `personalization`. |

```php
// Recognize a self-hosted analytics endpoint as "analytics".
add_filter( 'consentaro_tracker_signatures', function ( array $map ): array {
	$map['analytics.example.com'] = 'analytics';
	return $map;
} );
```

### `consentaro_tracker_content_signatures`

**File:** `src/Integration/TrackerSignatures.php`

Same idea as `consentaro_tracker_signatures`, but for **inline** scripts with no `src` to match against — matched against the raw script body instead.

| Parameter | Type | Description |
|---|---|---|
| `$map` | `array<string, string>` | Content fragment (e.g. a function call like `fbq(`) => category. |

```php
// Categorize a custom inline snippet as marketing.
add_filter( 'consentaro_tracker_content_signatures', function ( array $map ): array {
	$map['myVendorPixel('] = 'marketing';
	return $map;
} );
```

### `consentaro_script_blocking_enabled`

**File:** `src/Integration/ScriptBlocker.php`

Turns Consentaro's output-buffer-based script blocking off entirely for the current request — e.g. if it conflicts with a page builder. Checked after the banner-should-show gate, so it only matters on requests where blocking would otherwise run.

| Parameter | Type | Description |
|---|---|---|
| `$enabled` | `bool` | Whether script blocking should run. Default `true`. |

```php
// Disable script blocking on a specific incompatible landing page template.
add_filter( 'consentaro_script_blocking_enabled', function ( bool $enabled ): bool {
	return is_page_template( 'template-builder.php' ) ? false : $enabled;
} );
```

### `consentaro_gtm_id`

**File:** `src/Integration/GTM.php`

Overrides the GTM container ID read from Consentaro's saved settings. The result still has to pass Consentaro's own `GTM-XXXXXXX` format check, or it's treated as empty (GTM not loaded).

| Parameter | Type | Description |
|---|---|---|
| `$id` | `string` | The container ID, as saved in settings. |

```php
// Use a different GTM container on a staging environment.
add_filter( 'consentaro_gtm_id', function ( string $id ): string {
	return wp_get_environment_type() === 'staging' ? 'GTM-STAGE123' : $id;
} );
```

### `consentaro_woocommerce_track_events`

**File:** `src/Integration/WooCommerce.php`

Controls which WooCommerce dataLayer events Consentaro pushes (each still gated by the matching consent category having been granted).

| Parameter | Type | Description |
|---|---|---|
| `$events` | `array<string, bool>` | Keys `add_to_cart`, `purchase`, `view_item`, each `true`/`false`. All default `true`. |

```php
// Stop tracking view_item, keep add_to_cart and purchase.
add_filter( 'consentaro_woocommerce_track_events', function ( array $events ): array {
	$events['view_item'] = false;
	return $events;
} );
```

### `consentaro_woocommerce_anonymized_fallback`

**File:** `src/Integration/WooCommerce.php`

Off by default. When enabled, a WooCommerce event that *would* have been tracked but couldn't be (no consent yet) is instead queued anonymously (no personal data, capped at the last 50 entries) via `consentaro_woo_anon_queue` for later WP-Cron processing, instead of being dropped.

| Parameter | Type | Description |
|---|---|---|
| `$enabled` | `bool` | Whether to queue the anonymized fallback. Default `false`. |
| `$event` | `string` | Event name (e.g. `add_to_cart`, `purchase`, `view_item`). |
| `$ref` | `int` | Product or order ID the event relates to. |

```php
// Turn on the anonymized fallback, but only for purchase events.
add_filter( 'consentaro_woocommerce_anonymized_fallback', function ( bool $enabled, string $event, int $ref ): bool {
	return 'purchase' === $event;
}, 10, 3 );
```

### `consentaro_banner_html`

**File:** `src/Frontend/Banner.php`

Filters the final, fully-built banner + Customize-modal HTML right before it's echoed into `wp_footer`. Runs after all escaping, so treat anything you add here as your own responsibility to escape.

| Parameter | Type | Description |
|---|---|---|
| `$html` | `string` | The built banner markup. |
| `$settings` | `array<string, mixed>` | The full Consentaro settings array used to build it. |

```php
// Append a custom data attribute for an analytics tool to hook into.
add_filter( 'consentaro_banner_html', function ( string $html, array $settings ): string {
	return str_replace( 'id="consentaro-banner"', 'id="consentaro-banner" data-my-app="1"', $html );
}, 10, 2 );
```

### `consentaro_consent_log_query`

**File:** `src/Core/Plugin.php` (provider) · `src/Admin/ConsentLog.php` (`query()`)

A read-only provider for the optional Consent Log. Add-ons call `apply_filters( 'consentaro_consent_log_query', null, $args )` and get back a page of log rows plus the total match count, without touching Consentaro's database table directly. Returns an empty result (no error) if the Consent Log is turned off or its table doesn't exist.

| Parameter | Type | Description |
|---|---|---|
| `$default` | `null` | Always `null` from the caller; Consentaro replaces it with the result. |
| `$args` | `array<string, mixed>` | `page` (int, default 1), `per_page` (int, default 20, max 100), `action` (`accept-all`\|`deny-all`\|`customize`), `date_from` / `date_to` (`Y-m-d`, inclusive), `rid_search` (partial match), `orderby` (`created_at`\|`action`\|`rid`), `order` (`ASC`\|`DESC`). |

Returns `array{ items: array<int, array{id, rid, action, categories, created_at}>, total: int }`.

```php
// Latest 10 "deny all" decisions in the last week.
$result = apply_filters( 'consentaro_consent_log_query', null, array(
	'action'    => 'deny-all',
	'date_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
	'per_page'  => 10,
) );
echo $result['total'];
```

## Actions

### `consentaro_consent_updated`

**File:** `src/Consent/ConsentManager.php`

Fires right after the consent cookie is written — on every Accept All / Deny All / Customize decision, and on any later change of mind.

| Parameter | Type | Description |
|---|---|---|
| `$new` | `array<string, string>` | The newly saved consent states. |
| `$old` | `array<string, string>` | The states in effect just before this update. |

```php
// Log every consent change to your own analytics/CRM integration.
add_action( 'consentaro_consent_updated', function ( array $new, array $old ): void {
	if ( ( $new['analytics_storage'] ?? '' ) !== ( $old['analytics_storage'] ?? '' ) ) {
		error_log( 'Analytics consent changed to: ' . $new['analytics_storage'] );
	}
}, 10, 2 );
```

### `consentaro_settings_updated`

**File:** `src/Admin/Settings.php`

Fires after Consentaro's admin settings are saved (every `POST /consentaro/v1/settings` call), regardless of what changed.

| Parameter | Type | Description |
|---|---|---|
| `$current` | `array<string, mixed>` | The full settings array after this save. |
| `$previous` | `array<string, mixed>` | The full settings array as it was just before this save. |

```php
// Clear a page cache whenever the banner design changes.
add_action( 'consentaro_settings_updated', function ( array $current, array $previous ): void {
	if ( $current['banner'] !== $previous['banner'] ) {
		do_action( 'litespeed_purge_all' );
	}
}, 10, 2 );
```

### `consentaro_before_banner_display`

**File:** `src/Frontend/Banner.php`

Fires immediately before the banner HTML is echoed into `wp_footer` (only when the banner is actually about to render).

```php
add_action( 'consentaro_before_banner_display', function (): void {
	echo '<!-- Consentaro banner starts -->';
} );
```

### `consentaro_after_banner_display`

**File:** `src/Frontend/Banner.php`

Fires immediately after the banner HTML is echoed.

```php
add_action( 'consentaro_after_banner_display', function (): void {
	echo '<!-- Consentaro banner ends -->';
} );
```
