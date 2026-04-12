# Zbooking — WordPress Booking Plugin

**Version:** 3.1  
**Author:** zhovon  
**Plugin URI:** https://zhovon.com  
**Requires:** WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+

---

## Overview

Zbooking is a custom booking management system built for **homefoto.dk** — a professional real estate photography service. It provides a full two-step booking workflow (request → admin approval → customer confirmation) with WooCommerce integration, Danish localization, and secure, cache-safe data handling.

---

## Requirements

| Requirement | Minimum Version |
|-------------|----------------|
| WordPress   | 5.8            |
| PHP         | 7.4            |
| WooCommerce | 6.0            |
| MySQL       | 5.7            |

---

## Installation

1. Upload the `booking/` folder to `wp-content/plugins/`
2. In **WP Admin → Plugins**, activate **Zbooking**
3. Plugin creates two database tables automatically on activation:
   - `wp_zb_bookings` — all booking records
   - `wp_zb_addons` — your service catalogue
4. Go to **WP Admin → Zbooking** to add services before publishing your booking page
5. Create the required pages (see below) and assign shortcodes

> **Important after activation:** Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules so the WooCommerce "Mine bookinger" tab appears correctly.

---

## Pages to Create

| Page Title           | Slug            | Shortcode         | Notes |
|----------------------|-----------------|-------------------|-------|
| Book nu / Bookings   | `/bookings/`    | `[zbooking]`      | The main booking form |
| Login / Opret konto  | `/login/`       | `[zb_auth]`       | Combined login + signup on one page |
| Min konto            | `/min-konto/`   | `[zb_dashboard]`  | Customer portal (bookings, profile, password) |

> These are default slugs. You can change them in **WP Admin → Zbooking → Settings** for each website.

> All pages are automatically protected from page caching (WP Rocket, LiteSpeed, W3TC).

> **After deactivating Ultimate Member:** Point your login URL to `/login/` and your account URL to `/min-konto/`.

---

## Shortcodes

### `[zbooking]`

Renders the full multi-step booking form for logged-in customers.

**Where to place it:** A page with slug `/bookings/`.

**Behaviour:**
- Not logged in → redirected to your configured login slug with `redirect_to` parameter.
- Logged in → form pre-filled from saved account data.
- After submission → confirmation card shown with booking details and status.

**What the form collects:**

| Field | Required | Notes |
|-------|----------|-------|
| Firmanavn (Company) | ✅ | Pre-filled from account |
| Kontaktperson | ✅ | Pre-filled from account |
| E-mail | ✅ | Pre-filled from WP account |
| Telefon | ✅ | Pre-filled from account |
| Sælgers kontakt | ❌ | Seller name & phone |
| Ejendomsadresse | ✅ | Property address |
| Booket af | ✅ | Person making the booking |
| Services | ✅ | Clickable list from admin |
| Dato | ✅ | Min: tomorrow |
| Tidspunkt | ✅ | Dynamic available slot picker (15-min interval) |
| Kommentarer | ❌ | Free text |
| Rabatkode | ❌ | WooCommerce coupon |

**After submission:**
1. Booking saved to `wp_zb_bookings` with status `pending`
2. Admin receives an HTML email with one-click **Bekræft** / **Afvis** buttons + `.ics` calendar file
3. Customer receives a Danish acknowledgement email
4. Booking collisions are blocked server-side (same user and different users)

---

### `[zb_auth]`

Unified **Login + Sign Up** on a single page. Replaces the need for Ultimate Member or separate login/register pages.

**Where to place it:** A page with your configured login slug (default `/login/`).

**Behaviour:**
- Shows the **Login** form by default.
- A "Mangler du en konto? Opret her" button switches to the **Sign Up** form — no page reload.
- If already logged in → redirected to your configured dashboard slug.
- On successful signup → user is logged in and redirected to your configured booking slug.
- Admin receives a notification email on every new signup.

**Sign Up fields collected:**

| Field | Required | Saved to |
|-------|----------|----------|
| Firmanavn | ✅ | `company_name` + `billing_company` |
| Kontaktperson | ✅ | `contact_person` + `billing_first_name` |
| E-mail | ✅ | WP user email + `billing_email` |
| Telefon | ✅ | `phone` + `billing_phone` |
| Adresse | ✅ | `address` + `billing_address_1` |
| Adgangskode | ✅ | Min. 8 characters |

---

### `[zb_dashboard]`

Full **Customer Portal** with three tabs. Replaces the need for Ultimate Member account pages.

**Where to place it:** A page with your configured dashboard slug (default `/min-konto/`).

**Tabs:**

| Tab | What the customer can do |
|-----|--------------------------|
| **Bookinger** | View all bookings, status (colour-coded), price, coupon discount. Click **"Anmod om ny tid"** to request a reschedule (sends admin a notification email). Click **"Vis faktura"** to view a printable HTML invoice (only for Accepted bookings). |
| **Profil** | Update company name, contact person, phone, address. Optional profile picture upload. |
| **Sikkerhed** | Change account password (confirmed with repeat field). |

**Admin view:** When an Administrator visits the `[zb_dashboard]` page, they see the full admin bookings table instead of the customer portal.

**Invoice security:** Invoices are only accessible to the booking owner or an administrator. Direct URL access by other users is blocked.

---

## Admin — WordPress Dashboard

### Zbooking → Settings (new)

Navigate to **WP Admin → Zbooking → Settings** to configure:

| Group | Fields |
|------|--------|
| Slugs | Booking slug, Login slug, Dashboard slug |
| Slot rules | Slot interval, default duration, business start, business end |
| Outlook | Enable sync, Tenant ID, Client ID, Client Secret, Mailbox user |

Use this page for multi-website deployment so you do not hardcode URLs in code.

### Zbooking Menu (main menu, dashicons-calendar-alt)

Navigate to **WP Admin → Zbooking** to see a full table of all bookings with:

| Column | Description |
|--------|-------------|
| ID | Auto-incremented booking number |
| Firma | Company name |
| Kontaktperson | Contact person |
| E-mail | Clickable mailto link |
| Adresse | Property address |
| Ydelser | Selected services |
| Dato / Tid | Booking date and time |
| Pris | Price excl. VAT + discount if coupon applied |
| Status | `pending` / `Accepted` / `Rejected` |
| Handling | Opens the edit modal |

**Editing a booking status:**
1. Click **Rediger** on any row
2. Change the status in the dropdown
3. Click **Gem status**
4. An email is automatically sent to the customer on `Accepted` or `Rejected`

---

### Products → Booking Services (submenu)

Navigate to **WP Admin → Products → Booking Services** to manage your service catalogue.

**Adding a service:**

| Field | Description |
|-------|-------------|
| Servicenavn | Display name shown on the booking form |
| Beskrivelse | Optional internal description |
| Varighed (min) | Duration in minutes (summed automatically on form) |
| Pris (ekskl. moms) | Price excl. VAT in DKK |

Services appear immediately in the `[zbooking]` form as a clickable static list.

---

## Email Flow

### 1. Booking Request Submitted (automatic)

**To:** `booking@homefoto.dk`  
**Subject:** `[NY ANMODNING #42] Firma A/S – Vejnavn 1, 2100 København Ø`  
**Content:** Full booking details + one-click Confirm and Reject links

**To:** Customer email  
**Subject:** `Din booking-anmodning er modtaget – homefoto`  
**Content:** Summary of booking details, status: *Afventer bekræftelse*

---

### 2. Admin Clicks Confirm Link (one-click from email)

**To:** Customer email  
**Subject:** `✅ Din booking er bekræftet – faktura afventer fotografering | homefoto`  
**Content:** Full confirmation with date, time, services, price. Notes that payment is due after photography.

---

### 3. Admin Clicks Reject Link (one-click from email)

**To:** Customer email  
**Subject:** `Din booking-anmodning – opdatering | homefoto`  
**Content:** Polite rejection with invitation to contact `booking@homefoto.dk` to rebook.

---

## Customer Dashboard (WooCommerce My Account)

A **"Mine bookinger"** tab is added to the WooCommerce My Account page automatically.

**Customers see:**
- A table of all their own bookings
- Status (colour-coded: orange = pending, green = confirmed, red = rejected)
- Price with strike-through if a coupon was applied

**Administrators see:**
- The full admin bookings table (same as WP Admin → Zbooking)

---

## Booking Availability Engine (Industrial Mode)

Zbooking now enforces availability in two layers:

1. **Frontend dynamic slot generation**
- When date changes, available times are fetched via AJAX.
- Slots are generated in 15-minute steps (configurable in Settings).
- Slots are filtered by business hours and duration.

2. **Server-side final lock & conflict check**
- On submit, date/time interval is validated again.
- Overlap check runs against existing `pending` and `Accepted` bookings.
- Insert path applies a short DB table lock to reduce race-condition double booking.
- If Outlook is enabled, Graph calendar conflict is checked before save.

This blocks double booking for both same and different users.

---

## Outlook Integration (Microsoft Graph)

### What it does
- Reads Outlook calendar busy intervals while generating available slots.
- Re-checks Outlook conflict on final booking submit.
- Creates Outlook calendar event when booking status becomes `Accepted`.

### Setup steps
1. Register an app in Azure AD / Microsoft Entra.
2. Add application permission for Microsoft Graph calendar access.
3. Grant admin consent for the tenant.
4. Collect credentials:
    - Tenant ID
    - Client ID
    - Client Secret
    - Mailbox user (email or user ID)
5. Enter values in **WP Admin → Zbooking → Settings** and enable Outlook sync.

### Important note
If Outlook is enabled but Graph is unreachable, booking conflicts are treated conservatively to avoid overbooking.

---

## WooCommerce Coupon Integration

Zbooking uses native WooCommerce coupons. Create coupons at **WP Admin → WooCommerce → Coupons**.

Supported discount types:

| WC Coupon Type | Behaviour in Zbooking |
|---------------|----------------------|
| `fixed_cart`  | Fixed amount off total |
| `fixed_product` | Fixed amount off total |
| `percent`     | Percentage off total |

Coupons are validated via AJAX before form submission. Checks performed:
- Coupon exists in WooCommerce
- Usage limit not exceeded
- Expiry date not passed

On booking confirmation, the coupon usage count is incremented server-side.

---

## Security

| Layer | Implementation |
|-------|---------------|
| ABSPATH guard | All files start with `defined('ABSPATH') \|\| exit` |
| Nonces | Every form and AJAX call uses `wp_nonce_field` / `check_ajax_referer` |
| Login enforcement | Form handler redirects to login if not logged in |
| Sanitisation | All `$_POST` values go through `sanitize_*` before use |
| Output escaping | All output uses `esc_html`, `esc_attr`, `esc_url` |
| Object cache | `wp_cache_delete` called before `get_user_meta` to bypass Redis/Memcached |
| Page caching | `nocache_headers()` + `DONOTCACHEPAGE` constant prevents cached HTML leaking user data |
| Admin actions | Email action handler checks `current_user_can('manage_options')` |
| WC HPOS | `FeaturesUtil::declare_compatibility` declared for High-Performance Order Storage |

---

## Database Tables

### `wp_zb_bookings`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT AUTO_INCREMENT | Booking ID |
| `user_id` | BIGINT | WordPress user ID |
| `company_name` | VARCHAR(255) | |
| `contact_person` | VARCHAR(255) | |
| `booked_by` | VARCHAR(255) | Person who made the booking |
| `email` | VARCHAR(255) | |
| `phone` | VARCHAR(50) | |
| `price` | VARCHAR(50) | Original price excl. VAT |
| `coupon_price` | VARCHAR(50) | Price after coupon (if applied) |
| `coupon` | VARCHAR(100) | Coupon code used |
| `address` | TEXT | Property address |
| `seller_contact` | TEXT | Seller name/phone (optional) |
| `services` | TEXT | Comma-separated service names |
| `comments` | TEXT | Customer notes |
| `booking_date` | DATE | |
| `booking_time` | VARCHAR(10) | |
| `duration_minutes` | SMALLINT | Computed booking duration in minutes |
| `timeslot_start` | DATETIME | Slot start datetime (local WP timezone) |
| `timeslot_end` | DATETIME | Slot end datetime (local WP timezone) |
| `outlook_event_id` | VARCHAR(191) | Linked Outlook event ID (when accepted) |
| `status` | VARCHAR(20) | `pending` / `Accepted` / `Rejected` |
| `created_at` | DATETIME | Auto-set on insert |

### `wp_zb_addons`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT AUTO_INCREMENT | |
| `title` | VARCHAR(255) | Service name |
| `description` | TEXT | Internal description |
| `time` | SMALLINT | Duration in minutes |
| `price` | DECIMAL(10,2) | Price excl. VAT |
| `created_at` | DATETIME | |

---

## File Structure

```
booking/
├── zbooking.php                ← Main plugin file (Plugin Name: Zbooking)
├── README.md                   ← This file
├── assets/
│   ├── style.css               ← Front-end styles
│   └── zb-admin.js             ← Admin booking modal JavaScript
└── includes/
    ├── settings.php            ← Config defaults + slug/time helpers
    ├── outlook-sync.php        ← Microsoft Graph token/api/conflict/event service
    ├── db-table.php            ← Database schema (dbDelta) + data migration
    ├── registration.php        ← [zb_auth] shortcode (login + signup) + profile/password handlers
    ├── booking-form.php        ← [zbooking] shortcode + coupon AJAX + dynamic slots AJAX
    ├── form-handler.php        ← POST handler + server conflict lock + booking email dispatch
    ├── admin-page.php          ← WP Admin menus + status email handler
    ├── user-dashboard.php      ← [zb_dashboard] shortcode + admin table + AJAX status update
    └── invoice-template.php    ← Secure HTML invoice renderer
```

---

## Changelog

### v3.1 (2026-04-11)
- Added **Zbooking Settings** page for slug/time/Outlook configuration
- Replaced hardcoded URLs with configurable slug helpers
- Added dynamic available slot endpoint and UI for date/time selection
- Enforced 15-minute interval booking validation server-side
- Added interval overlap checks to block double bookings
- Added DB-backed slot fields: `duration_minutes`, `timeslot_start`, `timeslot_end`
- Added Outlook Graph service:
    - token handling
    - calendar conflict checks
    - accepted-booking event creation
- Added `outlook_event_id` persistence on accepted bookings

### v3.0 (2026-04-11) — PRO ULTIMATE
- **`[zb_auth]`** — unified Login + Sign Up on a single page (replaces Ultimate Member)
- **`[zb_dashboard]`** — full customer portal with Bookinger / Profil / Sikkerhed tabs (replaces Ultimate Member account pages)
- **Built-in HTML Invoice system** — printable invoices generated on-the-fly for Accepted bookings; secure access (owner or admin only)
- **Reschedule requests** — customers can request a new time; admin is notified by email
- **Profile picture upload** — optional avatar via WordPress Media Library
- **Password change** — self-service from the Sikkerhed tab
- **Admin notifications** — email sent on every new signup, new booking, and reschedule request
- **WooCommerce UI cleanup** — default Add-to-Cart buttons hidden; only "Book nu" remains on shop/product pages
- Complete clean rewrite of `user-dashboard.php` — zero duplicate functions

### v2.4 (2026-04-11)
- Full rename from OSF → Zbooking
- Added `DONOTCACHEPAGE` / `nocache_headers()` protection
- WooCommerce HPOS compatibility declared
- Admin one-click Confirm/Reject via `admin-post.php` with nonce
- ICS calendar file attached to confirmation emails
- `booked_by` field added to form, DB, and all email templates

### v2.3
- Initial refactor from original OSF plugin
- Danish localization of all UI, emails, and admin views
- Two-step booking workflow (pending → Accepted/Rejected)
- Payment-after-photography model (no upfront WC payment)
