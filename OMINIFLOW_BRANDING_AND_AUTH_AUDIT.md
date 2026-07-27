# OminiFlow Branding & Auth Audit

**Plugin:** Meta for WooCommerce (`facebook-for-woocommerce`)  
**Version audited:** 3.7.5  
**Audit date:** 2026-07-24  
**Scope:** Phase 1 — read-only inspection. No code was modified.

---

## Executive summary

This plugin **does not implement a standalone signup or login experience** (no email/password forms, no “Create Account” flow, no “Forgot Password” UI). Store owners authenticate with **WordPress admin credentials** first, then connect the store to Meta through **iframe-based onboarding hosted on Meta’s Commerce Partner Hub** or, in fallback/reconnect paths, **Facebook OAuth via WooCommerce’s connect proxy**.

There are **zero references** to `OminiFlow`, `ominiflow.com`, or OminiFlow auth APIs anywhere in this repository.

The reference OminiFlow signup/login screenshots mentioned in the requirements **were not found** in this workspace (no image assets attached to the repo or chat transcript files). Implementation will need those assets supplied explicitly.

---

## 1. Current signup flow

**There is no plugin-native signup flow.**

What exists instead:

| Step | What happens | Where |
|------|----------------|-------|
| 1 | Merchant installs plugin in WordPress | Standard WP plugin install |
| 2 | Merchant logs into **WordPress admin** (`wp-login.php`) | WordPress core — not plugin-owned |
| 3 | Merchant opens **Marketing → Facebook** (or **WooCommerce → Facebook**) | `includes/Admin/Enhanced_Settings.php` (`page=wc-facebook`) |
| 4 | If not connected, the **Shops** tab renders a full-page **iframe** pointing to Meta Commerce Partner Hub splash | `includes/Admin/Settings_Screens/Shops.php` → `MetaExtension::generate_iframe_splash_url()` |
| 5 | User completes setup **inside the iframe** (Meta/Facebook login & business setup — hosted externally) | `https://www.commercepartnerhub.com/commerce_extension/splash/` |
| 6 | Iframe sends `postMessage` event `CommerceExtension::INSTALL` on success | Inline JS in `Shops.php` → `generate_inline_enhanced_onboarding_script()` |
| 7 | Plugin saves tokens/settings via **WordPress REST API** | `POST /wp-json/wc-facebook/v1/settings/update` |
| 8 | Page reloads; connected merchants see management iframe or troubleshooting drawer | `Shops.php` |

**Legacy/alternate path (still in code, used for reconnect links):**

- `Connection::get_connect_url()` → redirects to `https://facebook.com/dialog/oauth`
- OAuth callback → WooCommerce proxy `https://api.woocommerce.com/integrations/v2/auth/facebook/`
- Token exchange → `https://api.woocommerce.com/integrations/v2/exchange/facebook/`
- Redirect back to store via `?wc-api=wc_facebook_connect`

This legacy OAuth path is **not** a custom signup form; it is Meta OAuth in a browser redirect.

**WhatsApp onboarding (separate flow):**

- Top-level admin menu **WhatsApp** (`page=wc-whatsapp`)
- Similar iframe pattern: splash → `CommerceExtension::WA_INSTALL` / `WA_CONNECT` postMessage → REST save
- Files: `includes/Admin/WhatsApp_Integration_Settings.php`, `includes/Handlers/WhatsAppExtension.php`

---

## 2. Current login flow

**There is no plugin-native login flow.**

| Layer | Mechanism |
|-------|-----------|
| WordPress admin access | Standard WP authentication (`current_user_can( 'manage_woocommerce' )`) |
| Meta / Facebook connection | OAuth or iframe onboarding (see above) — **not** email/password stored by this plugin |
| Plugin REST endpoints | Require logged-in WP user with `manage_woocommerce` capability + `X-WP-Nonce` |
| App Store login redirect | `Connection::handle_fbe_redirect()` — validates redirect host against Meta domains only |

Reconnect after failure uses a link to `get_connect_url()` (legacy OAuth), shown in admin notices in `Shops.php` and `Product_Sync.php`.

---

## 3. Current onboarding flow

### Primary (enhanced onboarding — default for new merchants)

Controlled by `WC_Facebookcommerce::use_enhanced_onboarding()` in `class-wc-facebookcommerce.php`:

- Returns `true` by default for new installs
- Returns `true` when connection token is invalid (forces re-onboarding iframe)
- Returns `false` only for **legacy connected stores** without `commerce_partner_integration_id`

**UI surface:**

```
WP Admin → Facebook settings (wc-facebook) → Shops tab
  └── <iframe id="facebook-commerce-iframe-enhanced" src="commercepartnerhub.com/...">
  └── postMessage listener (plugin-api-client.js + inline script)
  └── Optional troubleshooting drawer (sync buttons) when connected
```

**Iframe URL builder:** `includes/Handlers/MetaExtension.php`

- Splash: `{COMMERCE_HUB_URL}commerce_extension/splash/` + query params (app_id, external_business_id, shop metadata, client token)
- Management (connected): fetched from Meta Graph API `commerce_extension_uri`

### Secondary (WooCommerce onboarding task)

- `includes/Admin/Tasks/Setup.php` — WC Admin task “Advertise your products…”
- Action URL → plugin settings page (not a separate signup)

### Legacy onboarding artifacts (dead / unused UI)

- `assets/css/admin/facebook-for-woocommerce-connection.css` — styles `#wc-facebook-connection-box` but **no PHP renders this markup**
- `assets/css/facebook.css` — styles `#fbsetup` but **no PHP renders `#fbsetup` in current codebase**
- Referenced image assets (`logo.png`, `background.png`, `icon-0.png`, etc.) are **missing** from `assets/images/` (only `whatsapp-menu-icon.svg` and `ico-close.svg` exist)
- `facebook-for-woocommerce-connection.css` and `facebook-for-woocommerce-advertise.css` are **not enqueued** anywhere

---

## 4. Current authentication mechanism

| Type | Used for | Details |
|------|----------|---------|
| **WordPress session** | All admin UI & REST calls | Capability: `manage_woocommerce` |
| **WP REST nonce** | AJAX/REST from admin JS | `wp_create_nonce( 'wp_rest' )` passed to `GeneratePluginAPIClient()` |
| **Meta OAuth 2.0** | Legacy connect / reconnect | Facebook dialog → WC.com proxy → token exchange |
| **Iframe + postMessage** | Enhanced onboarding install/uninstall | Origin allowlist: `commercepartnerhub.com`, `facebook.com`, `business.facebook.com` |
| **Meta access tokens** | API calls after connect | Stored in WP options (`wc_facebook_access_token`, etc.) — server-side only |
| **Webhook verification** | Meta → store webhook | `includes/Handlers/WebHook.php` — `GET/POST /wp-json/wc-facebook/v1/webhook` |
| **Client tokens in URL** | Iframe splash params | `MetaExtension::CLIENT_TOKEN`, `WhatsAppExtension::CLIENT_TOKEN` (public client tokens, not user secrets) |

**Not present:** OminiFlow JWT/session auth, custom user registration, password reset, or frontend customer login.

---

## 5. Current API endpoints related to auth

### WordPress REST (`wc-facebook/v1`)

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/settings/update` | POST | WP admin + nonce | Save tokens/settings after iframe install |
| `/settings/uninstall` | POST | WP admin + nonce | Disconnect / clear integration |
| `/whatsapp_settings/update` | POST | WP admin + nonce | Save WhatsApp connection from iframe |
| `/whatsapp_settings/update/integration_config` | POST | WP admin + nonce | WhatsApp integration config |
| `/whatsapp_settings/uninstall` | POST | WP admin + nonce | Reset WhatsApp settings |
| `/whatsapp_settings/onboarding_complete` | POST | WP admin + nonce | Mark WA onboarding complete (`WA_CONNECT`) |
| `/webhook` | GET/POST | Webhook key validation | Meta FBE install webhook |
| `/extras` | GET | WP admin | FBE connection extras (legacy) |

Registered in: `includes/API/Plugin/Controller.php`, handlers under `includes/API/Plugin/`.

### External (Meta / WooCommerce — not owned by this plugin)

| URL | Role |
|-----|------|
| `https://www.commercepartnerhub.com/commerce_extension/splash/` | Enhanced onboarding UI (iframe) |
| `https://www.commercepartnerhub.com/whatsapp_utility_integration/splash/` | WhatsApp onboarding UI (iframe) |
| `https://facebook.com/dialog/oauth` | Legacy OAuth start |
| `https://api.woocommerce.com/integrations/v2/auth/facebook/` | OAuth proxy |
| `https://api.woocommerce.com/integrations/v2/exchange/facebook/` | Token exchange |
| `https://api.woocommerce.com/integrations/auth/facebookcommerce/` | Commerce onboarding redirect proxy |
| `https://api.woocommerce.com/integrations/app-store-login/facebook/` | App Store login flow |
| `https://www.facebook.com/commerce_manager/onboarding/` | Commerce manager onboarding |
| `https://api.facebook.com/whatsapp/business/{id}/utility_message_iframe_management_uri` | WhatsApp management iframe URI |

### OminiFlow (external — not in plugin)

Separate tooling on this machine references `https://whatsapp.ominiflow.com/api/wpbox/...` (WhatsApp messaging API probes). **That API is not wired into this WooCommerce plugin.**

---

## 6. Current branding / logo locations

| Location | Current branding | Notes |
|----------|------------------|-------|
| `facebook-for-woocommerce.php` | Plugin Name: **Meta for WooCommerce**, Author: **Meta** | WP plugin header |
| `readme.txt` | **Meta for WooCommerce** | WordPress.org listing |
| `includes/Admin/Enhanced_Settings.php` | Menu: **Facebook**, page title: **Meta for WooCommerce** | Admin menu labels |
| `includes/Admin/WhatsApp_Integration_Settings.php` | **WhatsApp for WooCommerce** menu | Uses `assets/images/whatsapp-menu-icon.svg` |
| Onboarding UI (iframe) | **Meta / Facebook** (hosted externally) | Not skinnable from plugin CSS alone |
| `assets/css/admin/facebook-for-woocommerce-shops.css` | Minimal iframe layout only | No logo/branding |
| `assets/css/admin/facebook-for-woocommerce-connection.css` | Legacy Meta-style connection box | **Unused**; references missing `logo.png` |
| `assets/css/facebook.css` | Legacy `#fbsetup` blue Meta styling | Partially used via `facebook-commerce.php` enqueue; markup likely removed |
| `assets/images/` | Only `whatsapp-menu-icon.svg`, `ico-close.svg` | **No Meta logo, no OminiFlow logo** |
| Admin notices / banners | “Meta for WooCommerce” copy | `PluginRender.php`, connection notices |

**OminiFlow logo:** **Not present** in repository. Must be added by product team (recommended path: `assets/images/ominiflow-logo.svg` or `.png`).

**Reference screenshots:** Not found in workspace — please provide files for design matching.

---

## 7. Files / components responsible for signup & login

| Component | File(s) | Role |
|-----------|---------|------|
| Enhanced onboarding shell | `includes/Admin/Settings_Screens/Shops.php` | Renders iframe + postMessage handler |
| Iframe URL generation | `includes/Handlers/MetaExtension.php` | Commerce Hub splash/management URLs |
| OAuth / legacy connect | `includes/Handlers/Connection.php` | OAuth URLs, token exchange, connect/disconnect handlers |
| Enhanced settings router | `includes/Admin/Enhanced_Settings.php` | Admin page, tabs, menu registration |
| REST settings persistence | `includes/API/Plugin/Settings/Handler.php` | Saves connection after iframe install |
| JS API client | `assets/js/admin/plugin-api-client.js` | REST client with nonce |
| REST bootstrap | `includes/API/Plugin/InitializeRestAPI.php` | Enqueues API client on settings pages |
| WhatsApp onboarding | `includes/Admin/WhatsApp_Integration_Settings.php`, `includes/Handlers/WhatsAppExtension.php` | Parallel iframe onboarding |
| WhatsApp REST | `includes/API/Plugin/WhatsAppSettings/Handler.php` | WA token/settings persistence |
| Onboarding feature flag | `class-wc-facebookcommerce.php` (`use_enhanced_onboarding()`) | Chooses enhanced vs legacy admin |
| Legacy admin (non-enhanced) | `includes/Admin/Settings.php` | Fallback settings without Shops iframe tab structure |
| WC onboarding task | `includes/Admin/Tasks/Setup.php` | Links to settings — not signup |

**No files implement:** signup form, login form, password fields, “Remember me”, terms checkbox, or OminiFlow auth.

---

## 8. Files / components responsible for branding

| Component | File(s) |
|-----------|---------|
| Plugin identity | `facebook-for-woocommerce.php`, `readme.txt` |
| Admin menu / page titles | `includes/Admin/Enhanced_Settings.php`, `includes/Admin/Settings.php`, `includes/Admin/WhatsApp_Integration_Settings.php` |
| Shops iframe layout CSS | `assets/css/admin/facebook-for-woocommerce-shops.css` |
| WhatsApp iframe CSS | `assets/css/admin/facebook-for-woocommerce-whatsapp-enhanced.css` |
| Legacy connection branding CSS (unused) | `assets/css/admin/facebook-for-woocommerce-connection.css` |
| Legacy setup CSS | `assets/css/facebook.css` |
| WhatsApp menu icon | `assets/images/whatsapp-menu-icon.svg` |
| Admin banners copy | `includes/Handlers/PluginRender.php` |
| Modal / banner UI | `assets/js/admin/modal.js`, `assets/js/admin/plugin-rendering.js` |

---

## 9. What can safely be changed (for OminiFlow branding)

**Low risk — visual shell around existing flows:**

- Add OminiFlow-branded **wrapper layout** around the iframe in `Shops.php` (left marketing column + right iframe/form area) matching reference screenshots
- New CSS file(s) for OminiFlow colors, typography, rounded cards, purple CTAs
- Add OminiFlow logo asset under `assets/images/` and reference from new wrapper
- Admin menu label / page title strings (careful: WordPress.org listing may still say Meta)
- Optional: rename visible headings in `Enhanced_Settings.php` / `Shops.php` only within the onboarding context
- WhatsApp admin page wrapper (`WhatsApp_Integration_Settings.php`) — if OminiFlow branding should extend there too

**Medium risk — requires product/API decisions:**

- Insert an **OminiFlow account gate** before showing Meta iframe (new admin page or modal) — needs OminiFlow auth API specification
- Replace reconnect links to go through OminiFlow login first, then Meta OAuth
- Filter hooks already exist for some URLs (`wc_facebook_connection_proxy_url`, `wc_facebook_connection_parameters`, etc.) — use only if OminiFlow officially replaces WC proxy (unlikely without Meta partnership)

**Do not change without explicit architecture approval:**

- `postMessage` event names and payloads (`CommerceExtension::INSTALL`, etc.)
- REST endpoint paths and settings field mapping in `Settings/Handler.php`
- Token storage option keys and `Connection.php` OAuth handshake
- Origin validation allowlists for iframe messaging
- Webhook endpoint and Meta Graph API integration

---

## 10. What must remain unchanged

- All product sync, catalog, feed, pixel, CAPI, and order event code paths
- `includes/Handlers/Connection.php` OAuth token exchange logic (unless OminiFlow becomes official auth broker with documented replacement)
- Iframe `postMessage` contract with Commerce Partner Hub
- REST API permission model (`manage_woocommerce` + nonces)
- Webhook handling (`includes/Handlers/WebHook.php`)
- WhatsApp utility message delivery (`WhatsAppExtension.php`, order status hooks)
- Offer management, rollout switches, crash recovery, background jobs
- Stored token option names and Meta API client behavior
- Frontend storefront behavior (pixel, events) — out of scope for signup/login anyway

---

## 11. Missing OminiFlow API / auth information

The following **must be provided before** implementing OminiFlow signup/login functionality (not guessable from this repo):

1. **OminiFlow authentication model**
   - Does OminiFlow replace WordPress admin login, Meta login, or sit as a pre-step?
   - Session/token format (JWT, cookie, API key per store)?

2. **API endpoints**
   - Signup URL / API (Full Name, Email, WhatsApp phone, Password)
   - Login URL / API
   - Forgot password / reset flow
   - Token refresh and logout
   - Base URL(s) — e.g. `https://whatsapp.ominiflow.com` vs separate auth domain

3. **Integration contract**
   - How OminiFlow account maps to `external_business_id` / WooCommerce site ID
   - Whether Meta connection still happens via Commerce Partner Hub iframe after OminiFlow login
   - Whether WooCommerce proxy OAuth URLs change

4. **Branding assets**
   - OminiFlow logo files (SVG/PNG @1x/@2x)
   - Meta Business Partners badge asset (if required in-plugin)
   - Exact color tokens (purple/magenta hex values)
   - Typography (font family — system vs hosted)

5. **Reference screenshots**
   - Not present in workspace — need PNG/Figma or image files for pixel-accurate implementation

6. **Legal / compliance**
   - Terms of Service and Privacy Policy URLs for checkbox links
   - Data handling: what PII is sent to OminiFlow vs stays in WP

---

## 12. Potential risks

| Risk | Description | Mitigation |
|------|-------------|------------|
| **No native signup/login exists** | Requirement assumes forms that are not in codebase | Treat as new admin UI shell + optional OminiFlow API integration, not “reskin existing forms” |
| **Iframe content is Meta-hosted** | Cannot rebrand the inside of Commerce Partner Hub from WP plugin CSS | OminiFlow branding applies to **wrapper** around iframe; Meta UI stays Meta unless Hub supports white-label |
| **Breaking postMessage flow** | Changing iframe IDs, origins, or event handlers breaks connect | Keep listener logic intact; only wrap presentation |
| **OAuth / token regression** | Editing `Connection.php` can break reconnect | Avoid unless OminiFlow officially replaces proxy |
| **Security** | Putting secrets in JS or hardcoding tokens | Keep all secrets server-side; use WP options + env/config |
| **WordPress.org compliance** | Plugin listed as official Meta plugin | Visible rebranding may conflict with Meta partnership guidelines — confirm with stakeholders |
| **Dead legacy CSS** | Missing image assets for old connection box | Do not revive legacy `#wc-facebook-connection-box` without restoring assets; prefer new OminiFlow components |
| **Dual onboarding paths** | Legacy OAuth vs enhanced iframe | Test both `use_enhanced_onboarding()` true/false and invalid-token reconnect |
| **Responsive admin** | WP admin has constrained layout | Test mobile/tablet admin views; iframe `min-height: calc(100vh - 200px)` may need adjustment |

---

## Summary blocks (required)

### CURRENT SIGNUP FLOW

No plugin signup. Flow is: **WordPress admin login → Facebook settings page → Meta Commerce Partner Hub iframe onboarding → REST settings save**. Legacy fallback: **Facebook OAuth redirect** via WooCommerce connect proxy.

### CURRENT LOGIN FLOW

No plugin login. **WordPress admin session** gates all plugin UI. **Meta/Facebook login** happens inside external iframe or OAuth redirect. Reconnect uses `Connection::get_connect_url()`.

### CURRENT AUTHENTICATION

**WordPress capabilities + REST nonces** for admin/API; **Meta OAuth + iframe postMessage** for Meta connection; **access tokens in WP options** (server-side). No OminiFlow auth.

### CURRENT BRANDING

**Meta for WooCommerce** / **Facebook** in plugin header, admin menus, and notices. Onboarding visual UI is **Meta-hosted in iframe**. Local assets: WhatsApp menu icon only. Legacy Meta connection CSS exists but is unused. **No OminiFlow assets.**

### FILES TO MODIFY (when implementation is approved)

| Priority | File | Reason |
|----------|------|--------|
| P0 | `includes/Admin/Settings_Screens/Shops.php` | Onboarding iframe shell — primary branding surface |
| P0 | New: `assets/css/admin/ominiflow-onboarding.css` (or similar) | OminiFlow visual system |
| P0 | New: `assets/images/ominiflow-logo.*` | Logo asset (to be supplied) |
| P1 | `includes/Admin/Enhanced_Settings.php` | Page title / tab labels (scoped carefully) |
| P1 | `includes/Admin/WhatsApp_Integration_Settings.php` | If WhatsApp onboarding should match OminiFlow |
| P1 | `assets/css/admin/facebook-for-woocommerce-shops.css` | Iframe container layout adjustments |
| P2 | New: `includes/Admin/OminiFlow_Auth.php` (or similar) | Only if OminiFlow signup/login API is specified |
| P2 | `includes/Handlers/Connection.php` | Only via existing filters — avoid core OAuth edits unless required |

### FILES NOT TO TOUCH

- `facebook-commerce-events-tracker.php`, `facebook-commerce-pixel-event.php`, `includes/Events/**`
- `includes/Feed/**`, `includes/Products/**`, `includes/Jobs/**`
- `includes/API/**` (except possibly new OminiFlow auth endpoint wrapper — not existing Meta handlers)
- `includes/Handlers/WebHook.php`
- `includes/OfferManagement/**`
- `includes/RolloutSwitches.php`, crash handler, pixel frontend JS
- `vendor/**`
- Core token mapping in `includes/API/Plugin/Settings/Handler.php` (unless additive-only)

### MISSING INFORMATION

- OminiFlow signup/login/register API documentation
- OminiFlow ↔ Meta/WooCommerce account linking rules
- Logo and brand asset files
- Reference screenshot files (not in workspace)
- Terms of Service / Privacy Policy URLs
- Stakeholder approval for visible “OminiFlow” vs “Meta for WooCommerce” naming on admin screens

### RECOMMENDED IMPLEMENTATION

**Phase 2 (after approval — not started in Phase 1):**

1. **Confirm architecture with stakeholders:** Is OminiFlow a **visual white-label shell** around existing Meta iframe onboarding, or a **new auth provider** before Meta connect?

2. **If visual-only (lowest risk, matches “do not rebuild plugin”):**
   - Add OminiFlow two-column layout in `Shops.php` around `#facebook-commerce-iframe-enhanced`
   - Left: logo, tagline (“easy, smarter, endless”), marketing copy, feature bullets, Meta Business Partners badge
   - Right: existing iframe (Meta login/onboarding unchanged)
   - Responsive CSS: stack columns on tablet/mobile
   - Do **not** change postMessage, REST, or OAuth code

3. **If OminiFlow auth is required before Meta connect:**
   - Add new admin-only pages/templates for Sign Up / Sign In (matching reference screenshots)
   - Implement server-side PHP proxy to OminiFlow APIs (never expose secrets in JS)
   - Gate iframe render behind OminiFlow session check
   - **Block implementation until API spec is delivered**

4. **Assets:** Add `assets/images/ominiflow-logo.svg` (or PNG) — **do not use text placeholder for logo**

5. **Test matrix:** New install, reconnect after invalid token, legacy non-enhanced path (`use_enhanced_onboarding()` false), WhatsApp tab, mobile admin width

---

*End of Phase 1 audit. Awaiting instruction: **“START OMINIFLOW BRANDING IMPLEMENTATION”** before any code changes.*
