# Consentaro — Product Roadmap

This is the forward-looking companion to `CHANGELOG.md` (which records what already
shipped). It's distilled from `Consentara_Market_Competitor_Strategy_Report.docx`
(market/competitor research, 26 Jul 2026) plus engineering suggestions added on top —
those are marked **[Suggestion]** so it's clear what came from the market report vs.
what was added from reading the actual codebase.

## Packaging legend

Per the strategy report's plan structure (see §10 of the report):

| Tag | Meaning |
|---|---|
| **Free** | Ships in the free WordPress.org plugin, no gating |
| **Pro** | Paid, 1–5 sites — automation, proof, advanced tracking |
| **Agency** | Paid, 25–100 sites — multisite, white-label, deployment |
| **Engineering** | Internal quality/infra work, not a user-facing feature, applies to all editions |

Boundary principle from the report (§13, Decision 5): **core trustworthy consent stays
Free; automation, proof depth, and agency workflow are premium.**

## Current state (v1.0.0 → v1.1.0)

- **v1.0.0** — Initial public release: consent banner (Accept/Deny/Customize), Consent
  Mode v2 defaults + updates, conditional GTM loading, basic EU/EEA geo helper,
  WooCommerce event gating (`view_item`, `add_to_cart`, `purchase`), cache-plugin
  compatibility, React admin (Guide/General/Design). — **Free**
- **v1.1.0** *(done this session, see `CHANGELOG.md`)* — Admin UX pass: fixed
  tab-switch data loss, GTM ID validation feedback, activation-redirect onboarding,
  header status badge, Guide checklist auto-sync, banner "Reset to defaults". —
  **Free**

Per the report's own feature-comparison table (§6), this baseline covers *banner +
Consent Mode + GTM + WooCommerce gating* but has **zero coverage** of automatic
scanning, script blocking, consent proof/log, multilingual, or agency workflow —
every mature competitor has all four of the first set. That gap is what the phases
below close.

## v1.2.0 — Trust Foundations (Free)

Report Phase 1 items that belong in the free core because they're about the plugin
being *correct*, not about being *automated* — per the Free/Pro boundary principle.

- Consent state engine hardening: default, update, revocation, and expiry handling
- WP Consent API integration + PHP/JS developer hooks (interoperability, costs
  nothing to give away, builds ecosystem trust)
- Accessibility baseline: keyboard nav, focus trap in the Customize modal, contrast,
  equal visual weight for Accept/Deny (report §Phase1 + current `banner.js` modal has
  no focus trap or Escape-key handling today — worth fixing here)
- **[Suggestion]** Settings schema versioning: add a `schema_version` key to
  `consentaro_settings` now, before the option shape grows in v1.3+ (blocker rules,
  log retention, region profiles). Cheap now, expensive to retrofit later.
- **[Suggestion]** Security/nonce audit of the public `/consentaro/v1/consent` REST
  route — it's `permission_callback: verifyPublicNonce`, worth confirming there's
  rate-limiting or abuse protection before a consent-log feature (v1.3) starts writing
  rows per request.
- **[Suggestion]** Frontend performance budget: enforce a max KB size for
  `banner.js`/`gtm-loader.js` in `webpack.config.js` (fails the build if exceeded) —
  operationalizes the report's Decision #1 ("frontend performance budget") instead of
  leaving it as a one-time promise.

## v1.3.0 — Diagnostics & Blocking (Pro launch)

This is where a real **Pro** edition needs to exist — report §10 puts diagnostics,
auto-blocker, and consent log all in the Pro column, and this is the biggest
competitive gap (§7, §6 comparison table).

- Consent Health Check: one-click scan reporting which scripts ran before consent,
  whether GCM default/update fired correctly, duplicate-GTM detection, red/amber/green
  output (report's top-priority differentiator, §8 item 1)
- Known-script blocker + iframe/content placeholder system (category-based, no full
  DOM rewrite — performance-safe per report §8 item 3)
- Consent proof/log: hashed visitor id, timestamp, policy version, region, selected
  categories, revocation history, configurable retention, CSV export
- **[Suggestion]** A `/consentaro/v1/health-check` REST endpoint mirroring the
  existing `/geo-check` pattern in `REST_Controller.php`, so the health check can
  reuse the same admin-only permission callback already in place.

## v1.4.0 — WooCommerce Advantage (Pro)

The report calls this the **key differentiator** in its Feature Priority Matrix
(§9) — "Very High" demand and differentiation, only Medium complexity.

- Expanded GA4 ecommerce events: `view_item_list`, `select_item`, `begin_checkout`,
  `refund` (current baseline only has `view_item`/`add_to_cart`/`purchase`)
- Ready-made consent recipes for Google Ads, Meta Pixel, TikTok Pixel, Microsoft Ads
- Duplicate-purchase-event prevention (explicitly called out in report §8 item 2)
- Checkout / order-received verification panel
- WooCommerce consent audit report + troubleshooting wizard

## v1.5.0 — Automation & Internationalisation (Pro)

- Service/cookie scanner with scan history (local/browser-assisted first; optional
  remote scan only if the user opts in — report §8 item 5 explicitly warns against a
  mandatory cloud crawler)
- Automatic categorisation with manual override + confidence score
- Region profiles: EU/UK opt-in, US opt-out + GPC, worldwide simple notice, as a
  banner rule engine (today's `GeoLocation.php` only has a binary EU/non-EU check)
- Polylang/WPML integration + per-language banner copy and category descriptions
- Cookie/service declaration shortcode or block (depends on scanner output, so it
  belongs in the same release)

## v2.0.0 — Agency & Scale (Agency tier)

- Preset export/import (JSON, signed), multisite network defaults, staging→production
  migration
- White-label consent audit PDF + client-facing status report
- Optional central cloud dashboard — **site status only, never consent data**, per
  report §11 distribution notes (keep this opt-in and clearly scoped to avoid the
  "cloud dependency" complaint the report levels at CookieYes/Cookiebot/Termly)
- Cross-domain consent sharing (where legally/technically appropriate)
- Deployment API, managed onboarding, priority support

## Ongoing / not tied to a single version

- **[Suggestion] Engineering** — Automated tests: PHPUnit for `ConsentManager` /
  `ConsentMode` / `Settings::saveSettings()` validation logic, Jest for the admin React
  components (`App.js` draft-state/dirty-tracking is exactly the kind of logic that
  silently regresses without a test)
- **[Suggestion] Engineering** — CI (GitHub Actions): `php -l`, `npm run build`, and
  `npm run lint:js` on every PR (lint currently fails locally on a `@typescript-eslint`
  version conflict in `node_modules` — worth pinning before wiring into CI)
- **[Suggestion] Engineering** — `uninstall.php` audit: confirm every option/transient
  this plugin creates (`consentaro_settings`, `consentaro_activation_redirect`, geo
  lookup transients) is actually removed on uninstall, and extend the check whenever
  v1.2+ adds new stored data (logs, scan history, presets)
- Messaging pillars from report §10, for use in readme.txt / marketing copy going
  forward: "Set up in minutes", "Built for WooCommerce", "Lightweight by design",
  "Know what is blocked", "Your data, your site" — and avoid absolute claims like
  "100% compliant" or "complete GDPR solution" (report §2, §12 risk table)

## Sources

- `Consentara_Market_Competitor_Strategy_Report.docx` — market/competitor/roadmap
  research, dated 26 Jul 2026 (note: written using the "Consentara" spelling; the
  product name is confirmed as **Consentaro**)
- `Elementor Landing Page Strategy for a WordPress Plugin.pdf` — landing-page/GTM
  structure, referenced for the messaging pillars and Free/Pro pricing display notes
- `CHANGELOG.md` — what has actually shipped so far
