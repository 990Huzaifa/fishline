# Fishline Authentication API Contracts

Base path: `/api`  
Content-Type: `application/json` (JSON body) or `multipart/form-data` / `application/x-www-form-urlencoded`  
Auth for protected routes: `Authorization: Bearer {token}`

Source of truth: `app/Http/Controllers/User/AuthController.php`, `routes/api.php`, `config/sanctum.php`.

---

## Shared conventions

### Success / error envelope

| Kind | Shape | Notes |
|------|--------|--------|
| Success (auth) | `{ "token": "...", "user": { ... } }` | Used by signin / social |
| Success (message) | `{ "message": "..." }` | Used by signup / password flows |
| Validation / app error | `{ "error": "<first message string>" }` | Almost always a **string**, not a field map |
| DB error | `{ "DB error": "<sql/exception message>" }` | Signup `400`, social/resend `500`, forgot/reset `400` |

There is **no** standard `{ "success": true/false }` wrapper.  
There is **no** refresh-token field anywhere.

### Access token

| Item | Value |
|------|--------|
| Field name | `token` (not `access_token`) |
| Mechanism | Laravel Sanctum personal access token |
| Format | `{tokenId}\|{plainText}` e.g. `12\|AbCdEf...` |
| Expiration | **None** (`config/sanctum.php` → `'expiration' => null`) |
| Refresh token | **Not supported** |
| Header | `Authorization: Bearer <token>` |
| Rotation | On each successful signin/social login, existing tokens for that user are deleted, then a new token is created |

### User object (`user`)

Returned as the Eloquent `User` model JSON. `password` and `remember_token` are hidden.

Typical shape:

```json
{
  "id": 1,
  "full_name": "John Doe",
  "username": "johndoea1b",
  "bio": null,
  "email": "john@example.com",
  "email_verified_at": null,
  "state": "Texas",
  "avatar": null,
  "personal_site": null,
  "google_id": null,
  "facebook_id": null,
  "rackscore_id": null,
  "apple_id": null,
  "fcm_token": "optional-fcm",
  "analysis_count": 0,
  "is_active": "active",
  "county": "Travis",
  "phone": null,
  "species": null,
  "listen_from": "Friend",
  "last_login_at": "2026-11-04T08:00:00.000000Z",
  "device_id": "device-uuid",
  "app_version": "1.0.0",
  "is_delete": 0,
  "created_at": "2026-11-04T07:00:00.000000Z",
  "updated_at": "2026-11-04T08:00:00.000000Z"
}
```

Notes:

- `is_active` is an enum string: `"active"` | `"inactive"`.
- Preferences (`chase`, `how_do_you_fish`, `measurement_unit`) are **not** embedded in this `user` object.
- Signin/social responses do **not** load relations into `user`.

### `chase` serialization

| Item | Contract |
|------|----------|
| Type | JSON **array** |
| Required | No (`nullable`) |
| Validation | `nullable\|array` only — item values are **not** enum-validated server-side |
| Request example | `"chase": ["Bass", "Trout", "Walleye"]` |
| Storage | `user_preferences.chase` JSON column |
| Not accepted | CSV string, single string, object map |

Correct:

```json
"chase": ["Largemouth Bass", "Redfish"]
```

Incorrect:

```json
"chase": "Bass,Trout"
```

```json
"chase": { "0": "Bass" }
```
(object may pass some clients but fails `array` validation if sent as object without list semantics; send a JSON array)

### `listen_from`

| Endpoint | Required? |
|----------|-----------|
| `POST /signup` | **Required** (string; no enum) |
| `POST /social` | **Not required** (not in social validator) |
| `POST /signin` | Not used |
| Password endpoints | Not used |

Missing on signup → `422` with `"error": "Listen from is required"`.

### Signup session / email verification

- Signup **does not** return a token.
- Signup **does not** create an authenticated session.
- There is **no** email-verification gate (`MustVerifyEmail` is unused; `email_verified_at` is not set).
- A welcome email is attempted after signup; failure is logged and **does not** fail signup.
- Client must call `POST /signin` (or social) afterward to obtain `token`.

### Social ID tokens

- Google/Apple/Facebook **ID tokens / JWTs are not verified server-side**.
- Client submits provider user IDs as plain fields:
  - Google → `google_id`
  - Apple → `apple_id`
  - Facebook → `facebook_id`
- There is **no** `id_token`, `access_token`, or `identityToken` request field on `POST /social`.

---

## 1. `POST /api/signup`

Creates a user + preferences. No auth token returned.

### Request

```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "device_id": "device-uuid",
  "listen_from": "Instagram",
  "app_version": "1.0.0",
  "fcm_token": "optional",
  "state": "Texas",
  "county": "Travis",
  "how_do_you_fish": "freshwater",
  "measurement_unit": "imperial",
  "chase": ["Bass", "Trout"]
}
```

| Field | Required | Rules |
|-------|----------|--------|
| `full_name` | yes | string |
| `email` | yes | email, unique in `users` |
| `password` | yes | any string (no min length on signup) |
| `device_id` | yes | any |
| `listen_from` | yes | any |
| `app_version` | no | stored if present |
| `fcm_token` | no | stored if present |
| `state` | no | string |
| `county` | no | string |
| `how_do_you_fish` | no | `saltwater` \| `freshwater` \| `icefishing` \| `all` (default `"all"`) |
| `measurement_unit` | no | `metric` \| `imperial` (default `"imperial"`) |
| `chase` | no | array |

### Success — `200`

```json
{
  "message": "Account Register successfully"
}
```

### Errors

| Status | Body | When |
|--------|------|------|
| `422` | `{ "error": "Full name is required" }` (or first validation message) | Validation failed |
| `422` | `{ "error": "Email already exists" }` | Duplicate email |
| `422` | `{ "error": "Listen from is required" }` | Missing `listen_from` |
| `400` | `{ "DB error": "..." }` | QueryException |

---

## 2. `POST /api/signin`

### Request

```json
{
  "email": "john@example.com",
  "password": "secret123",
  "fcm_token": "optional",
  "device_id": "device-uuid",
  "app_version": "1.0.0"
}
```

| Field | Required | Rules |
|-------|----------|--------|
| `email` | yes | email |
| `password` | yes | — |
| `fcm_token` | no | updated on user |
| `device_id` | no* | written to user (`null` if omitted) |
| `app_version` | no | updated if present |

\*Not validated as required, but controller always assigns `$request->device_id`.

### Success — `200`

```json
{
  "token": "12|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "username": "johndoea1b",
    "email": "john@example.com",
    "is_active": "active",
    "is_delete": 0,
    "last_login_at": "2026-11-04T08:00:00.000000Z",
    "device_id": "device-uuid",
    "app_version": "1.0.0",
    "fcm_token": "optional"
  }
}
```

### Errors

| Status | Body | When |
|--------|------|------|
| `422` | `{ "error": "Email is required" }` etc. | Validation |
| `404` | `{ "error": "User not found" }` | Unknown email |
| `403` | `{ "error": "Account is deactivated. Please contact support." }` | Inactive account check |
| `403` | `{ "error": "This account has been deleted" }` | `is_delete == 1` |
| `401` | `{ "error": "Invalid password" }` | Bad password |

---

## 3. `POST /api/forgot-password`

Sends a **4-digit** OTP email (`rand(1000, 9999)`). OTP is **not** returned in this response.

### Request

```json
{
  "email": "john@example.com"
}
```

### Success — `200`

```json
{
  "message": "Reset OTP sent successfully"
}
```

### Errors

| Status | Body | When |
|--------|------|------|
| `400` | `{ "error": "Email Address required" }` | Missing email |
| `400` | `{ "error": "Invalid Email" }` | Bad format |
| `400` | `{ "error": "Invalid Email Address" }` | Email not in `users` |
| `400` | `{ "DB error": "..." }` | QueryException |
| `500` | `{ "error": "..." }` | Other failures (e.g. mail) |

---

## 4. `POST /api/resend-code`

Replaces any existing OTP for the email and emails a new 4-digit code.

### Request

```json
{
  "email": "john@example.com"
}
```

### Success — `200`

```json
{
  "token": 4821
}
```

Important: unlike `forgot-password`, **this endpoint returns the OTP** in `token` (integer). Treat as implementation detail / security quirk; do not rely on exposing it in production UX if avoidable.

### Errors

| Status | Body | When |
|--------|------|------|
| `400` (thrown) → often surfaced as `500`* | `{ "error": "Email is required" }` | Validation |
| `404` (thrown) → often surfaced as `500`* | `{ "error": "User not found" }` | Unknown email |
| `500` | `{ "DB error": "..." }` | QueryException |
| `500` | `{ "error": "..." }` | Catch-all (this handler hardcodes status `500`) |

\*The catch block uses status `500` for all `Exception`s, even when the thrown code was `400`/`404`.

---

## 5. `POST /api/reset-password`

Two-phase flow using the same endpoint:

1. **Verify OTP only** — send `token`, omit `password`
2. **Set new password** — send `token` + `password` (min 8)

`token` here is the **OTP string**, not a Sanctum access token.

### Request — verify OTP

```json
{
  "token": "4821"
}
```

### Success (OTP verify) — `200`

```json
{
  "message": "OTP verified successfully"
}
```

OTP row is **kept** after verify-only.

### Request — reset password

```json
{
  "token": "4821",
  "password": "newpassword1"
}
```

### Success (password set) — `200`

```json
{
  "message": "Password reset successfully"
}
```

OTP row is deleted after successful password update. No auth token is returned.

### Errors

| Status | Body | When |
|--------|------|------|
| `400` | `{ "error": "Token required" }` | Missing token |
| `400` | `{ "error": "Password must be at least 8 characters" }` | Password too short when provided |
| `400` | `{ "error": "Invalid token" }` | OTP not found |
| `400` | `{ "DB error": "..." }` | QueryException |
| `400` | `{ "error": "..." }` | Other (catch forces `400`) |

---

## 6. `POST /api/social`

Login-or-register for Google / Apple / Facebook. Always returns a Sanctum `token`.

### Request

```json
{
  "provider": "google",
  "email": "john@example.com",
  "full_name": "John Doe",
  "device_id": "device-uuid",
  "google_id": "google-subject-id",
  "apple_id": null,
  "facebook_id": null,
  "fcm_token": "optional",
  "app_version": "1.0.0",
  "state": "Texas",
  "county": "Travis",
  "how_do_you_fish": "all",
  "measurement_unit": "imperial",
  "chase": ["Bass"]
}
```

| Field | Required | Rules |
|-------|----------|--------|
| `provider` | yes | `google` \| `apple` \| `facebook` |
| `full_name` | yes | — |
| `device_id` | yes | — |
| `email` | no | nullable email |
| `google_id` | if `provider=google` | plain provider user id string |
| `apple_id` | if `provider=apple` | plain provider user id string |
| `facebook_id` | if `provider=facebook` | plain provider user id string |
| `fcm_token` | no | updated on user |
| `app_version` | no | — |
| `state` / `county` | no | — |
| `how_do_you_fish` | no | same enum as signup |
| `measurement_unit` | no | same enum as signup |
| `chase` | no | array |
| `listen_from` | no | not validated on social |

Lookup: match by provider id **or** email. If found → login path (`already_registered: true`). If not → create user + preferences (`already_registered: false`), then issue token. Welcome email only for newly registered users with a non-empty email.

### Success — `200`

```json
{
  "token": "12|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "google_id": "google-subject-id",
    "apple_id": null,
    "facebook_id": null,
    "device_id": "device-uuid",
    "last_login_at": "2026-11-04T08:00:00.000000Z"
  },
  "already_registered": false
}
```

### Errors

| Status | Body | When |
|--------|------|------|
| `422` | `{ "error": "<first validation message>" }` | Validation |
| `500` | `{ "DB error": "..." }` | QueryException |
| `500` | `{ "error": "..." }` | Other |

---

## Flutter integration checklist

1. After signup → call signin (or social) to get `token`; do not expect a session from signup alone.
2. Persist Sanctum `token`; send as `Bearer`.
3. Do not implement refresh-token logic; tokens do not expire by config.
4. Send `chase` as a JSON array of species strings.
5. Always send `listen_from` on email/password signup.
6. Password reset OTP is 4 digits; verify via `reset-password` without `password`, then submit again with `password`.
7. Social: send `google_id` / `apple_id` / `facebook_id` (provider subject), **not** the OAuth ID token JWT — server does not verify ID tokens today.
8. Prefer matching on `{ "error": "..." }` string messages; field-level error maps are not used on these auth endpoints.
