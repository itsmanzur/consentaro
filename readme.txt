=== Consentaro - Cookie Consent & Google Consent Mode for WooCommerce ===
Contributors: itsmanzur
Tags: consent mode, cookies, gdpr, woocommerce, privacy
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-light Google Consent Mode v2 for WordPress. Simple cookie banner, GTM loading after choice, WooCommerce-ready.

== Description ==

Consentaro helps your site ask visitors for a clear cookie choice, remember that choice, and tell Google what is allowed — without a heavy cookie suite.

**Who it is for**

* Store owners using WooCommerce and Google tags
* Sites that need Google Consent Mode v2 without complexity
* Anyone who wants a small, fast consent banner

**What it does**

* Shows a friendly Accept / Deny / Customize banner when needed
* Sets Google Consent Mode v2 defaults (denied until the visitor chooses)
* Loads Google Tag Manager only after a choice (or when a banner is not required)
* Automatically holds known third-party trackers (Analytics, Meta Pixel, TikTok Pixel, and more) inert until the matching consent category is granted, with a review screen to override any script's category
* Design settings now include live preview (desktop/mobile), border-radius, button layout, font size, and one-click preset themes
* A new Insights tab shows anonymous daily Accept/Deny/Customize counts — no visitor data, IP addresses, or individual records are ever stored
* Keyboard-accessible consent banner — focus management, focus trap, and Escape support in the Customize dialog
* Fully translation-ready admin interface (188+ strings) and frontend banner
* Optional Consent Log — off by default, keeps an anonymized proof-of-consent record (no IP addresses or personal identifiers) for sites that need it for compliance audits
* Optionally focuses the banner on EU/EEA-style visitors (geo helper)
* Gates WooCommerce dataLayer events (view item, add to cart, purchase) by consent
* Plays nicely with popular page caches (WP Rocket, LiteSpeed, W3 Total Cache)
* Includes a plain-English Guide tab in wp-admin

**What it does not do**

* It is not legal advice
* It does not replace a full privacy policy or lawyer review for your region

Open **Consentaro → Guide** after activation for a non-technical walkthrough.

== Installation ==

1. Upload the `consentaro` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **Consentaro** through the **Plugins** screen.
3. Open **Consentaro** in the admin menu.
4. Read the **Guide** tab, then set your options under **General** and **Design**.
5. (Optional) Enter your GTM container ID, for example `GTM-XXXXXXX`.
6. Test in a private/incognito window: Accept / Deny should hide the banner and remember the choice.

== Frequently Asked Questions ==

= Do I need Google Tag Manager? =

No. The banner and Consent Mode defaults work without GTM. Add a GTM ID when you are ready to load tags after consent.

= Why don’t I see the banner? =

Try a private window (an earlier choice may be saved). Confirm the plugin is enabled. If “Geo-based banner” is on and you are outside the EU, the banner may stay hidden on purpose — turn Geo off temporarily to test.

= Will this slow my site down? =

Consentaro is built to stay light. Front-end assets are small and load only when needed.

= Does it work with WooCommerce? =

Yes. Shop events are sent to the data layer only when the visitor’s consent allows them.

= Will script blocking break scripts I actually need? =

It's built to be conservative: only scripts matching a known tracker signature (Google Analytics, Meta Pixel, TikTok Pixel, and similar) are held back, and only until the matching consent category is granted. Anything Consentaro doesn't recognize — including your theme, page builder, and WooCommerce scripts — is always left alone. You can also review every detected script under **Consentaro → Scripts** and set its category by hand.

= Is this a complete GDPR solution by itself? =

Consentaro helps with Consent Mode and a clear visitor choice. Rules differ by country and business. This plugin does not replace legal advice.

= Does Consentaro track individual visitors? =

No. The Insights tab only stores anonymous daily aggregate counts (how many Accept / Deny / Customize decisions happened each day). No per-visitor data, IP addresses, cookie IDs, or timestamp-level records are ever stored.

= Can Consentaro keep a record of individual consent decisions? =

Yes, optionally — it is off by default. Turn on "Keep a log of consent decisions" under General and Consentaro stores, per decision: a timestamp, which categories were granted/denied, and a random non-identifying token (so you can tell a returning browser changed its choice later, without knowing who they are). It never stores an IP address, user agent, or any other personal identifier. Records are exportable as CSV and are automatically pruned after the retention period you choose (1–24 months, default 12).

== Translations ==

Consentaro is translation-ready (text domain: `consentaro`). If this plugin is hosted on WordPress.org, you can contribute a translation at [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/consentaro/) — no local setup needed.

For local development, a starter `languages/consentaro.pot` is included. Regenerate it after changing any translatable string with WP-CLI:

`wp i18n make-pot . languages/consentaro.pot --exclude=assets/src,node_modules,vendor`

== Screenshots ==

1. Guide tab — plain-English overview and checklist
2. General settings — enable, GTM ID, geo option
3. Scripts tab — detected third-party scripts with category overrides
4. Design settings — banner text, position, colors, live preview
5. Design tab — live preview, theme presets, shape & layout controls
6. Insights tab — anonymous daily consent-decision chart
7. General settings — Consent Log toggle, retention period, and CSV export

== Credits ==

Consentaro is developed and maintained by itsmanzur. All code, design, and assets in this plugin are original work created specifically for Consentaro.

Source code: https://github.com/itsmanzur/consentaro

== External services ==

This plugin can connect to the following third-party services. Both are optional and only activate based on your own configuration; they are not used to track visitors on Consentaro's behalf.

**Google Tag Manager (Google LLC)**
If you enter your own Google Tag Manager (GTM) container ID in the plugin settings, Consentaro loads the standard GTM container script from Google's servers (googletagmanager.com) so that your GTM tags can run. The script is only loaded in the visitor's browser after a consent decision has been recorded (or immediately, with Google Consent Mode v2 default/denied state, if you have not required a banner), consistent with Google Consent Mode v2. No data is sent to Consentaro or to us; any data transmitted to Google is governed by your own GTM/Google Analytics configuration and Google's terms.
This service is provided by Google LLC: [Terms of Service](https://marketingplatform.google.com/about/analytics/terms/us/), [Privacy Policy](https://policies.google.com/privacy).

**ip-api.com (geolocation, optional)**
If geo-targeting is enabled in the plugin settings and the visitor's country cannot be determined from your hosting provider (e.g. a Cloudflare header) or server-level GeoIP data, Consentaro sends the visitor's IP address, server-side, to ip-api.com to determine their country. This is only used to decide whether the consent banner should be displayed (e.g. to EU/EEA/UK/CH visitors) and is not stored beyond a short server-side cache. No personal data is sent other than the IP address already present in the request.
This service is provided by ip-api.com: [Terms of Service](https://ip-api.com/docs/legal), [Privacy Policy](https://ip-api.com/docs/legal).

== Changelog ==

= 1.5.0 =
* Added: optional Consent Log (off by default) — records anonymized proof-of-consent (timestamp, category choices, a random non-identifying token) for sites that need an audit trail; includes a configurable retention period and CSV export
* Added: full translation support for the admin interface via wp_set_script_translations() — all admin UI strings are now translatable, not just PHP-side text
* Fixed: consent banner is now fully keyboard-accessible — focus moves into the banner and Customize dialog automatically, Tab is trapped inside the dialog while open, Escape closes it, and focus returns to the triggering button
* Fixed: aria-modal was incorrectly applied to non-modal banner positions (top/bottom/corner bars); only the true modal position and the Customize dialog now use it
* Improved: visible focus outline on all banner buttons and checkboxes across every theme preset
* Added: PHPUnit + CI test suite covering tracker categorization, consent-state sanitization, Insights aggregation, and the Consent Log's off-by-default guarantee

= 1.4.0 =
* Changed: admin UI fully re-themed with Consentaro's brand colors (previously used WordPress's default blue throughout)
* Added: live preview in the Design tab — desktop/mobile toggle, clickable Accept/Deny/Customize buttons that simulate the real consent flow
* Added: border-radius, button layout (inline/stacked), button alignment, and font-size controls for the banner
* Added: four one-click theme presets (Consentaro, Minimal, Dark, Warm)
* Added: **Insights** tab — anonymous daily consent-decision counts (Accept/Deny/Customize) with a 7/30/90-day chart; no per-visitor data, IP addresses, or individual records are stored
* Improved: category dropdown in the Scripts tab now uses a fully brand-styled listbox
* Improved: setup checklist in the Guide tab shows a success state at 100% completion
* Improved: save confirmation now fades in/out smoothly

= 1.3.0 =
* Added: script blocking — recognized third-party trackers (Google Analytics, Meta Pixel, TikTok Pixel, LinkedIn Insight, Hotjar, and more) are automatically held inert in the page until the visitor grants the matching consent category, both for tags loaded from an external file and for inline snippets pasted directly into the theme
* Added: **Scripts** tab under Consentaro settings — review every third-party script detected on the site, see its auto-assigned category, and override it per script
* Added: `consentaro_tracker_signatures` and `consentaro_tracker_content_signatures` filters so developers can extend or correct auto-detection
* Added: unrecognized scripts (theme, page builder, WooCommerce, payment scripts) are never touched — detection is conservative by design

= 1.2.0 =
* Security: Cloudflare-supplied geo/IP headers (CF-IPCountry, CF-Connecting-IP) are no longer trusted by default, since a site not actually proxied through Cloudflare could have these headers spoofed to force incorrect country detection and bypass the consent banner
* Added: "This site is behind Cloudflare" toggle on the General tab — enable only if your site genuinely proxies traffic through Cloudflare, to restore the faster header-based geo detection

= 1.1.0 =
* Security: the WooCommerce dataLayer event script now uses hex-escaped JSON output, closing a stored-XSS path where a product/order field containing "</script>" could break out of the inline script tag
* Fixed: uninstall cleanup was matching a stale transient prefix and never removed cached geolocation lookups from the database
* Fixed: all internal CSS classes, data attributes, and inline-script markers now consistently use the "consentaro-" prefix instead of a leftover "cf-" shorthand from an earlier plugin name, avoiding collisions with other plugins/services using that prefix
* Fixed: raised the declared minimum WordPress version to 6.6 — the admin bundle depends on the `react-jsx-runtime` script handle, which core only registers from 6.6 onward; on 6.4/6.5 the settings page's React app failed to mount
* Fixed: the settings-updated hook now receives the actual previous settings instead of a duplicate of the new ones
* Fixed: switching admin tabs before saving could silently discard unsaved General/Design edits
* Fixed: an invalid GTM Container ID was silently dropped on save with no warning
* Fixed: the Guide tab's "enabled" checklist item could show a false state, disconnected from the real toggle
* Fixed: added the standard direct-file-access guard to all class files flagged by Plugin Check
* Fixed: silenced a Plugin Check nonce-verification warning in the activation handler with justification (only an isset() check on a WP-core-set flag, no user input is trusted)
* Added: unsaved-changes warning when leaving the admin page
* Added: inline validation message on the GTM Container ID field
* Added: one-time redirect to the Guide tab right after activation
* Added: Active / Disabled status badge in the admin header
* Added: "Reset to defaults" button on the Design tab

= 1.0.0 =
* Initial public release
* Google Consent Mode v2 defaults and updates
* Cookie banner with Accept / Deny / Customize
* Conditional Google Tag Manager loading
* Geo helper for banner display
* WooCommerce event gating (view_item, add_to_cart, purchase)
* Cache compatibility for WP Rocket, LiteSpeed Cache, and W3 Total Cache
* React admin: Guide, General, and Design tabs

== Upgrade Notice ==

= 1.2.0 =
Security hardening: Cloudflare geo/IP headers are now ignored unless you explicitly confirm your site is behind Cloudflare (new toggle on the General tab). If your site is behind Cloudflare, enable the toggle after updating to keep fast geo detection.

= 1.1.0 =
Includes a security fix (stored-XSS in the WooCommerce dataLayer event output — update recommended for all WooCommerce sites), plus admin usability improvements: no more lost edits when switching settings tabs, clearer GTM ID validation, and a guided first-activation experience.

= 1.0.0 =
First public release of Consentaro.
