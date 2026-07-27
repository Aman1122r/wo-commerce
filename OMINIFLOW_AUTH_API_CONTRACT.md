# OminiFlow Auth API Contract

**Status:** Required before production — **not available in this repository today**

This document defines the exact API contract the WooCommerce plugin expects for OminiFlow signup/login. Do not invent endpoints; implement against this contract once OminiFlow provides official auth documentation.

---

## Repository search summary (2026-07-24)

The following were searched across this plugin and related local projects:

| Search target | Result |
|---------------|--------|
| OminiFlow signup/login endpoints in `facebook-for-woocommerce` | **Not found** |
| OminiFlow auth env vars / constants in plugin | **Not found** |
| OminiFlow auth helpers / clients in plugin | **Not found** (new abstraction added: `Auth_Config`, `Auth_Client`) |
| `whatsapp.ominiflow.com/api/wpbox/*` | Found only in **separate** Shopify integration (`shopify-final--integration-main`) — WhatsApp template messaging, **not user auth** |
| WordPress options / REST routes for OminiFlow auth | **Not found** |

**Conclusion:** Real OminiFlow user authentication must be supplied externally via WordPress filters (see Integration below).

---

## Required endpoints

When using the default HTTP client (`Auth_Client`), configure:

```
wc_ominiflow_auth_api_base_url → https://<your-auth-host>/api/v1/auth
```

The client appends:

| Action | Default path | Method | Content-Type |
|--------|--------------|--------|--------------|
| Signup | `{base_url}/signup` | POST | `application/json` |
| Login  | `{base_url}/login`  | POST | `application/json` |

Override paths with:

- `wc_ominiflow_auth_signup_path` (default: `signup`)
- `wc_ominiflow_auth_login_path` (default: `login`)

---

## Signup request body

```json
{
  "full_name": "Jane Merchant",
  "email": "merchant@example.com",
  "phone_country": "+91",
  "phone": "9876543210",
  "password": "********",
  "source": "woocommerce",
  "site_url": "https://store.example.com/"
}
```

### Expected success response (2xx)

```json
{
  "success": true,
  "user_id": "string-or-number",
  "access_token": "optional-session-or-jwt",
  "message": "optional human-readable message"
}
```

### Expected error response (4xx/5xx)

```json
{
  "success": false,
  "message": "Human-readable error for the merchant"
}
```

---

## Login request body

```json
{
  "email": "merchant@example.com",
  "password": "********",
  "source": "woocommerce",
  "site_url": "https://store.example.com/"
}
```

### Expected success response (2xx)

Same shape as signup success. The plugin does **not** store OminiFlow passwords. Optional `access_token` may be handled by a custom filter on `wc_ominiflow_login_complete`.

---

## Forgot password

There is **no reset API** in this repository.

Production requires a **real external URL** via:

```php
add_filter( 'wc_ominiflow_forgot_password_url', function () {
    return 'https://app.ominiflow.com/forgot-password';
} );
```

When this filter is empty, the Forgot Password link is **hidden** (not faked).

---

## Custom integration (recommended for non-REST auth)

If OminiFlow auth does not match the default JSON POST contract, implement:

```php
add_filter( 'wc_ominiflow_auth_is_configured', '__return_true' );

add_filter( 'wc_ominiflow_auth_api_request', function ( $result, $action, $credentials, $password, $base_url ) {
    if ( 'signup' === $action ) {
        // Call OminiFlow signup API; return array on success or WP_Error on failure.
    }
    if ( 'login' === $action ) {
        // Call OminiFlow login API; return array on success or WP_Error on failure.
    }
    return $result;
}, 10, 5 );
```

Return `WP_Error` on failure — the gate **must not** pass on error.

---

## Security requirements

1. Never expose OminiFlow API secrets in frontend JavaScript.
2. Never store merchant passwords in WordPress options or post meta.
3. All auth AJAX requires `manage_woocommerce` + `wc_ominiflow_auth` nonce.
4. Passwords are sent server-to-server only inside `Auth_Client`.

---

## Gate behavior after successful auth

On successful signup/login:

1. User meta `wc_ominiflow_onboarding_authenticated` is set for the current WP admin user.
2. Meta Commerce Partner Hub iframe is revealed.
3. Existing `CommerceExtension::INSTALL` postMessage → REST token save flow runs unchanged.

On auth **not configured**:

- Signup/login UI renders with a visible notice.
- Submit returns HTTP 503 + `wc_ominiflow_auth_not_configured`.
- Meta iframe is **not** revealed.

---

## Related OminiFlow services (not auth)

The Shopify integration uses WhatsApp messaging only:

- Base: `https://whatsapp.ominiflow.com`
- Path: `/api/wpbox/sendtemplatemessage`
- Env: `OMINIFLOW_BASE_URL`, `OMINIFLOW_API_KEY`

These are **not** signup/login endpoints and must not be used for user authentication.
