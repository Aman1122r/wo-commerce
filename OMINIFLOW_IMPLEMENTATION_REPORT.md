# OminiFlow Implementation Report

**Plugin:** Meta for WooCommerce (`facebook-for-woocommerce`)  
**Version:** 3.7.5  
**Date:** 2026-07-24  
**Scope:** OminiFlow branding/onboarding UI layer only

---

## MODIFIED FILES

| File | Change |
|------|--------|
| `includes/Admin/Settings_Screens/Shops.php` | Integrated OminiFlow auth gate + onboarding shell around existing Meta iframe; enqueues new CSS/JS on onboarding path only |

---

## NEW FILES

| File | Purpose |
|------|---------|
| `includes/Admin/OminiFlow/Auth_Gate.php` | Signup/login gate state (user meta), AJAX handlers, optional remote API hooks |
| `includes/Admin/OminiFlow/Onboarding_Shell.php` | Two-column OminiFlow signup/login + Meta iframe shell markup |
| `assets/css/admin/ominiflow-onboarding.css` | OminiFlow purple/magenta branding, responsive layout |
| `assets/js/admin/ominiflow-onboarding.js` | Signup/login form UX, reveals Meta iframe after gate success |
| `assets/images/ominiflow-logo.svg` | Placeholder vector logo asset (replace with official OminiFlow logo) |
| `assets/images/meta-business-partner-badge.svg` | Meta Business Partner badge for onboarding marketing panel |
| `OMINIFLOW_IMPLEMENTATION_REPORT.md` | This report |

---

## EXISTING FUNCTIONALITY PRESERVED

The following were **not modified**:

| Area | Status |
|------|--------|
| `includes/Handlers/Connection.php` (OAuth/token exchange) | Unchanged |
| `includes/Handlers/MetaExtension.php` (iframe URL generation) | Unchanged |
| `includes/Handlers/WhatsAppExtension.php` | Unchanged |
| `includes/API/Plugin/**` (REST routes & handlers) | Unchanged |
| `includes/Handlers/WebHook.php` | Unchanged |
| WordPress options keys (`wc_facebook_*`) | Unchanged |
| postMessage event names (`CommerceExtension::INSTALL`, `::RESIZE`, `::UNINSTALL`, WhatsApp `WA_*`) | Unchanged |
| Iframe element ID `#facebook-commerce-iframe-enhanced` | Unchanged |
| `generate_inline_enhanced_onboarding_script()` in `Shops.php` | Unchanged |
| `assets/js/admin/plugin-api-client.js` | Unchanged |
| WooCommerce product/order/catalog/sync logic | Unchanged |
| WhatsApp admin page (`WhatsApp_Integration_Settings.php`) | Unchanged |
| Plugin header, admin menu labels (“Meta for WooCommerce”) | Unchanged |

### Integration contract preserved

- Meta iframe still loads from Commerce Partner Hub URLs built by `MetaExtension`.
- postMessage → `POST /wp-json/wc-facebook/v1/settings/update` flow is untouched.
- Connected merchants with valid tokens still see the **management iframe** and troubleshooting drawer exactly as before (no OminiFlow gate).

---

## OMINIFLOW FEATURES ADDED

1. **Two-column onboarding shell** on the Shops tab (marketing left, signup/login right) matching the reference layout:
   - OminiFlow logo + tagline “easy, smarter, endless”
   - Meta Business Partner badge
   - Feature highlights
   - Signup form: Full Name, Email, WhatsApp Phone (+ country code), Password, Confirm Password, Terms checkbox, Create Account
   - Login form: Email, Password, Remember Me, Forgot Password, Login, link to Sign Up

2. **Auth gate** before first Meta iframe display for new/unconnected stores:
   - Uses new user meta `wc_ominiflow_onboarding_authenticated` (does not replace existing options)
   - AJAX via `admin-ajax.php` actions `wc_ominiflow_signup` / `wc_ominiflow_login`
   - After successful gate, Meta iframe is revealed; all Meta postMessage/REST behavior proceeds unchanged

3. **Extensibility hooks** (no hardcoded secrets):
   - `wc_ominiflow_show_auth_gate`
   - `wc_ominiflow_logo_url`
   - `wc_ominiflow_auth_api_base_url`
   - `wc_ominiflow_auth_api_request`
   - `wc_ominiflow_forgot_password_url`
   - `wc_ominiflow_terms_url` / `wc_ominiflow_privacy_url`
   - `wc_ominiflow_signup_complete` / `wc_ominiflow_login_complete`

4. **Responsive CSS** for desktop, tablet, and mobile within WP admin.

---

## DOCUMENTED CONFLICTS / DESIGN DECISIONS

### 1. No OminiFlow auth API in repository

**Conflict:** Reference signup/login implies real OminiFlow account creation; no OminiFlow auth API is documented in this plugin.

**Resolution:** Implemented a **local admin gate** with server-side validation. When `wc_ominiflow_auth_api_base_url` filter is empty (default), signup/login validates fields locally and marks the gate passed — **does not create OminiFlow accounts remotely**. Wire real auth via filters when API spec is available.

### 2. Official OminiFlow logo not supplied

**Conflict:** Audit requirement: do not use plain text instead of logo; official asset was not in workspace.

**Resolution:** Added `assets/images/ominiflow-logo.svg` as a **replaceable vector placeholder** with purple/magenta mark. Replace this file with the official OminiFlow logo (same path) or override via `wc_ominiflow_logo_url` filter.

### 3. Meta iframe content remains Meta-hosted

**Conflict:** Cannot rebrand inside Commerce Partner Hub iframe from WordPress.

**Resolution:** OminiFlow branding applies to the **wrapper only**; Meta onboarding UI inside the iframe is unchanged.

### 4. Reconnect with invalid token

**Conflict:** Merchants reconnecting after token invalidation should not be blocked by OminiFlow gate.

**Resolution:** Gate is skipped when `connection_invalid && has_merchant_token` (existing reconnect path preserved).

---

## TESTS PERFORMED

| Test | Result |
|------|--------|
| PHP CLI syntax check | **Not run** — `php` not available in shell PATH on this machine |
| Static review: no changes to Connection.php, REST handlers, postMessage script | **Pass** |
| Static review: iframe ID and event names unchanged | **Pass** |
| Static review: no existing option keys modified | **Pass** |
| Static review: connected-store path uses original iframe wrapper | **Pass** |
| JS review: no modifications to `plugin-api-client.js` | **Pass** |
| Full WordPress runtime regression (activate plugin, admin UI, OAuth, iframe) | **Not run** — requires WordPress + WooCommerce environment |

---

## KNOWN ISSUES

1. **OminiFlow remote authentication not wired** — signup/login gate is local until `wc_ominiflow_auth_api_base_url` or `wc_ominiflow_auth_api_request` is implemented.
2. **Logo is a placeholder SVG** — replace with official OminiFlow brand asset.
3. **Forgot Password** shows a message unless `wc_ominiflow_forgot_password_url` filter is set.
4. **WhatsApp onboarding page** is not OminiFlow-branded in this pass (Shops/Facebook onboarding only).
5. **PHP runtime tests** could not be executed locally (PHP not in PATH).

---

## MANUAL TESTS REQUIRED

Run these in a WordPress + WooCommerce staging site with this plugin:

### Existing functionality (must all pass)

- [ ] 1. Plugin activates successfully
- [ ] 2. No PHP fatal errors in debug.log
- [ ] 3. No JavaScript console errors on Facebook settings page
- [ ] 4. WooCommerce checkout, products, orders still work
- [ ] 5. Meta integration settings save correctly when connected
- [ ] 6. Meta iframe loads after OminiFlow gate (Commerce Partner Hub)
- [ ] 7. postMessage `CommerceExtension::INSTALL` saves via REST and reloads
- [ ] 8. postMessage `CommerceExtension::RESIZE` resizes iframe
- [ ] 9. REST `POST /wp-json/wc-facebook/v1/settings/update` works
- [ ] 10. Legacy OAuth reconnect (`get_connect_url()`) still works
- [ ] 11. Token storage in `wc_facebook_*` options unchanged
- [ ] 12. WhatsApp admin iframe + `WA_*` postMessage still works
- [ ] 13. Product/catalog sync still works
- [ ] 14. Order/event handling unchanged
- [ ] 15. Other settings tabs (Product Sync, Attributes) still work

### OminiFlow features

- [ ] 16. OminiFlow signup UI renders on Shops tab (unconnected store)
- [ ] 17. OminiFlow login UI renders and toggles correctly
- [ ] 18. Logo loads from `assets/images/ominiflow-logo.svg`
- [ ] 19. Responsive layout on desktop, tablet, mobile
- [ ] 20. After signup/login, Meta iframe appears and onboarding completes
- [ ] 21. Connected store skips OminiFlow gate and shows management iframe directly

---

## NEXT STEPS (recommended)

1. Replace `assets/images/ominiflow-logo.svg` with the official OminiFlow logo from your reference screenshots.
2. Provide OminiFlow auth API documentation; implement via `wc_ominiflow_auth_api_request` filter.
3. Set `wc_ominiflow_forgot_password_url` to the production password-reset URL.
4. Run the manual test checklist above in staging before production deployment.
5. Optionally extend the same shell to `WhatsApp_Integration_Settings.php` if WhatsApp onboarding should match OminiFlow branding.
