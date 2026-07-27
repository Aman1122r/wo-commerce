# OminiFlow Final Audit

**Plugin:** Meta for WooCommerce (`facebook-for-woocommerce`) v3.7.5  
**Audit date:** 2026-07-24  
**Scope:** Post-fix review after production-readiness corrections

---

## A. What was implemented

| Component | Description |
|-----------|-------------|
| OminiFlow onboarding shell | Two-column signup/login UI wrapping Meta Commerce Partner Hub iframe on Shops tab |
| Official logo integration | Uses provided PNG asset (see Section B) |
| Auth abstraction | `Auth_Config`, `Auth_Client`, `Auth_Gate` — fail-closed, no fake local auth |
| API contract doc | `OMINIFLOW_AUTH_API_CONTRACT.md` |
| Gate routing | New/unconnected → OminiFlow UI → Meta iframe; connected/reconnect paths preserved |

### Files in scope

**Modified:** `includes/Admin/Settings_Screens/Shops.php`

**Added:**
- `includes/Admin/OminiFlow/Auth_Config.php`
- `includes/Admin/OminiFlow/Auth_Client.php`
- `includes/Admin/OminiFlow/Auth_Gate.php`
- `includes/Admin/OminiFlow/Onboarding_Shell.php`
- `assets/css/admin/ominiflow-onboarding.css`
- `assets/js/admin/ominiflow-onboarding.js`
- `assets/images/ominiflow-logo.png`
- `assets/images/meta-business-partner-badge.svg`
- `OMINIFLOW_AUTH_API_CONTRACT.md`
- `OMINIFLOW_FINAL_AUDIT.md`

**Removed:** `assets/images/ominiflow-logo.svg` (generated placeholder — deleted)

---

## B. Official logo asset used

| Property | Value |
|----------|--------|
| **Canonical path** | `assets/images/ominiflow-logo.png` |
| **Source provided by product team** | `assets/Screenshot 2026-07-24 112059.png` (official OminiFlow logo banner) |
| **Resolution** | Copied from provided screenshot to canonical path; placeholder SVG removed |
| **Displayed on** | Signup panel, login panel, onboarding marketing column (single shared logo in shell) |
| **Fallback** | If PNG missing, resolves to URL-encoded screenshot path; filter `wc_ominiflow_logo_url` overrides |
| **Tagline handling** | Duplicate text tagline hidden when official PNG is used (logo already includes tagline) |

**Verified:** `assets/images/ominiflow-logo.png` exists (30,970 bytes).  
**Verified:** `assets/images/ominiflow-logo.svg` does **not** exist (placeholder removed).

---

## C. Authentication status

| Item | Status |
|------|--------|
| OminiFlow signup/login API in repository | **NOT FOUND** |
| Local-only fake auth | **REMOVED** — gate no longer passes without real API |
| Default behavior when unconfigured | UI renders + visible notice; submit returns 503 `wc_ominiflow_auth_not_configured` |
| Production-ready auth | **NO** — blocked until API wired |
| Integration path | `wc_ominiflow_auth_api_base_url` + default JSON client, or `wc_ominiflow_auth_api_request` custom handler |
| Contract documentation | See `OMINIFLOW_AUTH_API_CONTRACT.md` |

### Repository search performed

- Plugin PHP/JS/CSS: no OminiFlow auth URLs, env vars, or clients (pre-existing)
- Related Shopify project: `OMINIFLOW_BASE_URL` + `/api/wpbox/sendtemplatemessage` only (WhatsApp messaging, **not auth**)
- External probe script: WhatsApp message status endpoints only

**Signup/login are NOT fully functional today.** UI is ready; backend requires OminiFlow auth API per contract doc.

---

## D. Forgot password status

| Item | Status |
|------|--------|
| Reset API in repository | **NOT FOUND** |
| Fake reset flow | **NOT IMPLEMENTED** |
| UI when URL unconfigured | Forgot Password link **hidden** |
| UI when URL configured | External link via `wc_ominiflow_forgot_password_url` filter (opens in new tab) |
| Production-ready | **NO** — requires real OminiFlow forgot-password URL |

---

## E. Existing functionality preserved

Static review confirms **no modifications** to:

| Area | File(s) | Verified |
|------|---------|----------|
| Meta OAuth / token exchange | `includes/Handlers/Connection.php` | No OminiFlow references; file untouched |
| REST API handlers | `includes/API/Plugin/**` | No OminiFlow references |
| Meta iframe URL builder | `includes/Handlers/MetaExtension.php` | Untouched |
| WhatsApp integration | `includes/Admin/WhatsApp_Integration_Settings.php`, `WhatsAppExtension.php` | Untouched |
| Webhooks | `includes/Handlers/WebHook.php` | Untouched |
| postMessage events | `Shops.php` inline script | `CommerceExtension::INSTALL`, `::RESIZE`, `::UNINSTALL` unchanged |
| Iframe element ID | `#facebook-commerce-iframe-enhanced` | Unchanged |
| Token option keys | `wc_facebook_*` | Unchanged |
| plugin-api-client.js | `assets/js/admin/plugin-api-client.js` | Untouched |

### Gate behavior matrix

| Scenario | Expected | Implementation |
|----------|----------|----------------|
| New / unconnected store | OminiFlow signup/login → Meta iframe | Gate shown when `!is_connected && !has_merchant_token` |
| Already connected (valid token) | Management iframe directly | Early return via `show_management_iframe` — **no gate** |
| Reconnect / invalid token (has prior token) | Meta splash/reconnect directly | Gate skipped when `connection_invalid && has_merchant_token` |
| Auth not configured | Cannot proceed to Meta iframe via fake auth | Fail-closed; notice shown |

---

## F. Static tests completed

| Test | Result |
|------|--------|
| Official logo PNG exists | **PASS** |
| Placeholder SVG removed | **PASS** |
| All OminiFlow PHP/JS/CSS files present | **PASS** |
| Asset path resolution in `Onboarding_Shell::resolve_official_logo_url()` | **PASS** (file_exists check) |
| Connection.php unchanged | **PASS** (grep) |
| REST API layer unchanged | **PASS** (grep) |
| postMessage event names unchanged | **PASS** |
| JavaScript syntax (`node --check ominiflow-onboarding.js`) | **Not run / unavailable** (Node not confirmed in shell) |
| PHP syntax (`php -l`) | **Not run** — PHP not in PATH on audit machine |
| WordPress plugin activation | **Not run** — no WP environment |
| Live iframe / OAuth / REST | **Not run** — no WP environment |

---

## G. Live WordPress tests still required

Do **not** treat this plugin as production-ready until these pass in staging:

1. Plugin activates without PHP fatals
2. Connected store → management iframe, no OminiFlow gate
3. Invalid-token reconnect → Meta splash iframe, no OminiFlow gate
4. New unconnected store → OminiFlow UI with official logo visible
5. Signup/login blocked with clear message when auth API not configured
6. After wiring real auth API → signup/login succeed → Meta iframe → `CommerceExtension::INSTALL` saves tokens
7. WhatsApp admin page unchanged
8. WooCommerce products/orders/settings unaffected
9. Browser console free of JS errors on Shops tab
10. Responsive layout on desktop/tablet/mobile

---

## H. Exact remaining blockers before production

| # | Blocker | Owner action |
|---|---------|--------------|
| 1 | **OminiFlow auth API not in repo** | Provide official signup/login API spec + base URL; implement via filters or extend `Auth_Client` |
| 2 | **Auth not wired** | Configure `wc_ominiflow_auth_api_base_url` or `wc_ominiflow_auth_api_request` in production mu-plugin/theme |
| 3 | **Forgot password URL missing** | Set `wc_ominiflow_forgot_password_url` to real OminiFlow reset page |
| 4 | **No live WordPress regression** | Run Section G checklist in staging |
| 5 | **Optional:** WhatsApp onboarding not OminiFlow-branded | Extend shell to `WhatsApp_Integration_Settings.php` if required |

---

## Production readiness verdict

**NOT PRODUCTION-READY**

- Branding/onboarding UI: **ready for staging review**
- Official logo: **integrated**
- Authentication: **blocked** — UI only until OminiFlow auth API is connected
- Forgot password: **blocked** — URL not configured
- Meta/WooCommerce core integration: **preserved by static audit** — pending live confirmation
