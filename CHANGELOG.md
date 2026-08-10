# Changelog

All notable changes to Consentaro are documented here, split by edition (Free / Pro)
so it's clear at a glance what shipped where. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/).

## [1.1.0] — 2026-07-26 — Admin UX pass

### Rebrand

- Renamed the plugin from **ConsentFlow** to **Consentaro**: PHP namespace
  (`ConsentFlow\*` → `Consentaro\*`), option name (`consentflow_settings` →
  `consentaro_settings`), text domain (`consentflow` → `consentaro`), REST
  namespace (`/consentflow/v1` → `/consentaro/v1`), constants
  (`CONSENTFLOW_*` → `CONSENTARO_*`), admin CSS prefix
  (`consentflow-admin__*` → `consentaro-admin__*`), main file
  (`consentflow.php` → `consentaro.php`).

### Consentaro (Free)

#### Bug fixes

- **Unsaved admin edits could silently disappear.** The General and Design
  tabs each kept their own local draft state; since WordPress's `TabPanel`
  unmounts inactive tabs, switching tabs before clicking Save discarded
  whatever was typed — with no warning. Draft state now lives in `App.js`
  and survives tab switches.
  — `assets/src/admin/App.js`, `GeneralTab.js`, `DesignTab.js`
- **Invalid GTM Container ID failed silently.** Saving a malformed GTM ID
  (not matching `GTM-XXXXXXX`) was quietly dropped server-side, but the UI
  still showed a plain "Settings saved" success message — admins had no way
  to know the value wasn't actually stored. The save response now reports
  which field was rejected, and the UI shows a specific warning instead.
  — `src/Admin/Settings.php`, `assets/src/admin/components/GeneralTab.js`
- **Guide checklist could show a false "enabled" state.** The "Turn
  Consentaro on in General" checklist item was a separate manual checkbox
  stored in `localStorage`, independent of the real toggle — it could be
  ticked without the plugin actually being enabled, or vice versa. It's now
  derived directly from the live settings value and can't drift out of sync.
  — `assets/src/admin/components/GuideTab.js`

#### New features

- Unsaved-changes browser warning (`beforeunload`) when leaving the admin
  page with unsaved edits.
- Inline validation message on the GTM Container ID field.
- One-time redirect to **Consentaro → Guide** right after plugin activation,
  so new admins land on the walkthrough instead of having to find the menu
  (skipped on bulk/network activation).
- Active / Disabled status badge in the admin header, visible on every tab.
- "Reset to defaults" button on the Design tab to undo banner styling
  changes without retyping the original values.

### Consentaro Pro

_No Pro edition exists yet._ The Free/Pro split referenced in
`deep-research-report.md` is currently a business/marketing plan only —
there is no separate Pro codebase or license-gated functionality in this
repository. This section is a placeholder for when that split ships.

## [1.0.0] — Initial public release

See `readme.txt` for the original 1.0.0 changelog entry (Consent Mode v2,
cookie banner, conditional GTM loading, geo helper, WooCommerce event
gating, cache compatibility, React admin with Guide/General/Design tabs).
