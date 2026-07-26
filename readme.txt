=== Consentaro - Cookie Consent & Google Consent Mode for WooCommerce ===
Contributors: itsmanzur
Tags: consent mode, cookies, gdpr, woocommerce, privacy
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
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

= Is this a complete GDPR solution by itself? =

Consentaro helps with Consent Mode and a clear visitor choice. Rules differ by country and business. This plugin does not replace legal advice.

== Screenshots ==

1. Guide tab — plain-English overview and checklist
2. General settings — enable, GTM ID, geo option
3. Design settings — banner text, position, colors, live preview

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

= 1.1.0 =
* Fixed: switching admin tabs before saving could silently discard unsaved General/Design edits
* Fixed: an invalid GTM Container ID was silently dropped on save with no warning
* Fixed: the Guide tab's "enabled" checklist item could show a false state, disconnected from the real toggle
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

= 1.1.0 =
Admin usability fixes: no more lost edits when switching settings tabs, clearer GTM ID validation, and a guided first-activation experience.

= 1.0.0 =
First public release of Consentaro.
