=== ConsentFlow ===
Contributors: itsmanzur
Tags: consent mode, cookies, gdpr, woocommerce, privacy
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-light Google Consent Mode v2 for WordPress. Simple cookie banner, GTM loading after choice, WooCommerce-ready.

== Description ==

ConsentFlow helps your site ask visitors for a clear cookie choice, remember that choice, and tell Google what is allowed — without a heavy cookie suite.

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

Open **ConsentFlow → Guide** after activation for a non-technical walkthrough.

== Installation ==

1. Upload the `consentflow` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **ConsentFlow** through the **Plugins** screen.
3. Open **ConsentFlow** in the admin menu.
4. Read the **Guide** tab, then set your options under **General** and **Design**.
5. (Optional) Enter your GTM container ID, for example `GTM-XXXXXXX`.
6. Test in a private/incognito window: Accept / Deny should hide the banner and remember the choice.

== Frequently Asked Questions ==

= Do I need Google Tag Manager? =

No. The banner and Consent Mode defaults work without GTM. Add a GTM ID when you are ready to load tags after consent.

= Why don’t I see the banner? =

Try a private window (an earlier choice may be saved). Confirm the plugin is enabled. If “Geo-based banner” is on and you are outside the EU, the banner may stay hidden on purpose — turn Geo off temporarily to test.

= Will this slow my site down? =

ConsentFlow is built to stay light. Front-end assets are small and load only when needed.

= Does it work with WooCommerce? =

Yes. Shop events are sent to the data layer only when the visitor’s consent allows them.

= Is this a complete GDPR solution by itself? =

ConsentFlow helps with Consent Mode and a clear visitor choice. Rules differ by country and business. This plugin does not replace legal advice.

== Screenshots ==

1. Guide tab — plain-English overview and checklist
2. General settings — enable, GTM ID, geo option
3. Design settings — banner text, position, colors, live preview

== Changelog ==

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

= 1.0.0 =
First public release of ConsentFlow.
