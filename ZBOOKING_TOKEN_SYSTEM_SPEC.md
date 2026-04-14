# ZBooking License Token System - Complete Specification

## Overview
A simple token issuance system where WordPress plugin users request a license token from a Next.js portfolio page, save it to their plugin settings, and the plugin verifies tokens on each request.

---

## System Architecture

### 1. Next.js Portfolio (zhovon.com)

#### Route 1: Token Request Page
- **Path**: `/zb-token`
- **Type**: Public page (no auth required)
- **Purpose**: User fills email → generates token → displays token
- **UI Elements**:
  - Email input field
  - "Generate Token" button
  - Success display showing generated token
  - Copy-to-clipboard button

#### Route 2: Token Generation API
- **Path**: `/api/internal/zbooking/license/generate`
- **Method**: POST
- **Auth**: None (open, rate-limited recommended)
- **Request Body**:
  ```json
  {
    "email": "customer@example.com",
    "plan": "pro"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "token": "zbk_abc123xyz789...",
    "plan": "pro",
    "max_domains": 1,
    "message": "Token generated successfully"
  }
  ```
- **Logic**:
  1. Validate email format
  2. Generate random secure token (at least 32 chars)
  3. Insert into `zbooking_license_keys` table:
     - `token`: generated token
     - `customer_email`: email from form
     - `plan`: pro (hardcoded or form param)
     - `status`: active
     - `max_domains`: 1 (or plan-based)
     - `expires_at`: null (never expire) or 1 year from now
  4. Return success with token

#### Route 3: Token Verification API
- **Path**: `/api/internal/zbooking/license/verify`
- **Method**: POST
- **Auth**: Shared secret check
- **Request Body** (from WordPress plugin):
  ```json
  {
    "token": "zbk_abc123xyz789...",
    "secret_key": "aspirine",
    "domain": "booking.example.com",
    "plugin": "zbooking",
    "plugin_ver": "1.0.0"
  }
  ```
- **Response**:
  ```json
  {
    "valid": true,
    "plan": "pro",
    "expires_at": null,
    "plugin": "zbooking"
  }
  ```
  or
  ```json
  {
    "valid": false,
    "reason": "invalid_token|expired|revoked|domain_limit|bad_secret"
  }
  ```
- **Logic**: (same as earlier spec)
  1. Check shared secret matches `ZBOOKING_SHARED_SECRET` env var
  2. Query `zbooking_license_keys` by token
  3. Check status = active
  4. Check expiry if set
  5. Check domain binding or add new domain
  6. Log to `zbooking_license_verification_logs`
  7. Return result

---

### 2. WordPress Plugin (booking plugin)

#### Admin Settings Page Update
- **Location**: Plugin settings page (already exists)
- **New Button**: "Get License Token"
- **Button Action**: Opens new tab to `https://zhovon.com/zb-token`
- **Code Location**: `includes/settings.php`

#### Plugin Settings Fields (already exist)
- License Token (text input)
- Verify URL: `https://zhovon.com/api/internal/zbooking/license/verify`
- Shared Secret: `aspirine` (default)

---

## Supabase Tables (already created)

### Table: `zbooking_license_keys`
```sql
id, token, customer_email, plan, status, max_domains, expires_at, plugin, notes, created_at, updated_at
```

### Table: `zbooking_license_domain_bindings`
```sql
id, license_id, domain, first_seen_at, last_seen_at
```

### Table: `zbooking_license_verification_logs`
```sql
id, token, domain, ok, reason, ip, user_agent, created_at
```

---

## Build Checklist

### Next.js Portfolio (zhovon.com)
- [ ] Create page `/app/zb-token/page.tsx`
  - [ ] Email input form
  - [ ] "Generate Token" button with loading state
  - [ ] Success display with token + copy button
  - [ ] Error messages
- [ ] Create API route `/app/api/internal/zbooking/license/generate/route.ts`
  - [ ] POST handler
  - [ ] Email validation
  - [ ] Token generation (`randomBytes` or library)
  - [ ] Supabase insert into `zbooking_license_keys`
  - [ ] Response formatting
- [ ] Create API route `/app/api/internal/zbooking/license/verify/route.ts`
  - [ ] POST handler
  - [ ] Secret key validation
  - [ ] Supabase query `zbooking_license_keys`
  - [ ] Domain binding logic
  - [ ] Logging to `zbooking_license_verification_logs`
  - [ ] Response formatting
- [ ] Environment variables (`.env.local`):
  - [ ] `SUPABASE_URL`
  - [ ] `SUPABASE_SERVICE_ROLE_KEY`
  - [ ] `ZBOOKING_SHARED_SECRET=aspirine`

### WordPress Plugin (booking plugin)
- [ ] Update `includes/settings.php`
  - [ ] Add "Get License Token" button
  - [ ] Link to `https://zhovon.com/zb-token`
- [ ] Verify `/includes/settings.php` already has:
  - [ ] License token input field
  - [ ] Verify URL field
  - [ ] Shared secret field
  - [ ] Token verification caching logic (transient)

---

## Flow Diagram

```
Customer visits zhovon.com/zb-token
         ↓
Fills email, clicks "Generate Token"
         ↓
Next.js API generates random token
         ↓
Token saved to Supabase zbooking_license_keys
         ↓
Token displayed on page
         ↓
Customer copies token
         ↓
Customer goes to WordPress plugin settings
         ↓
Customer pastes token in "License Token" field
         ↓
Customer saves settings
         ↓
Plugin verifies token by calling Next.js verify API
         ↓
Token validation cached in transient
         ↓
Plugin initialized with valid token
```

---

## Security Notes
1. Shared secret (`aspirine`) must match between WordPress plugin settings and Next.js env var
2. Verify API should rate-limit to prevent token enumeration
3. Token length: at least 32 chars (use `crypto.randomBytes(24).toString('hex')` in Node.js)
4. Domain binding prevents token reuse across different domains
5. All verification responses log to `zbooking_license_verification_logs` for audit

---

## Quick Reference: Token Format
Example: `zbk_a7f9e2d1c4b8f3e9a2d7e1f9c3b6a8d2f5e1a9c0d3b6e9f2a5c8d0e3f1a4b`
- Prefix: `zbk_` (identifies as ZBooking token)
- Body: 64-char hex string (256-bit random)

---

## Files to Create/Modify

### Create in Next.js:
1. `app/zb-token/page.tsx` - Token request form page
2. `app/api/internal/zbooking/license/generate/route.ts` - Token generation
3. `app/api/internal/zbooking/license/verify/route.ts` - Token verification

### Modify in WordPress Plugin:
1. `includes/settings.php` - Add "Get Token" button linking to zhovon.com/zb-token

### Already Complete:
- Supabase tables
- Plugin verification transient logic
- Plugin settings fields
