# Zbooking — WordPress Booking Plugin

**Version:** 2.4  
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

| Page Title         | Slug            | Shortcode       |
|--------------------|-----------------|-----------------|
| Book nu / Bookings | `/bookings/`    | `[zbooking]`    |
| Opret konto        | `/opret-konto/` | `[zb_signup]`   |

> Both pages are automatically protected from page caching (WP Rocket, LiteSpeed, W3TC).

---

## Shortcodes

### `[zbooking]`

Renders the full multi-step booking form for logged-in customers.

**Where to place it:** On your booking page (e.g., a page with slug `/bookings/`).

**Behaviour:**
- If the visitor is **not logged in**, they are redirected to `/login?redirect_to=/bookings/` automatically.
- If the visitor **is logged in**, the form appears pre-filled with their saved company name, contact person, and phone number (read fresh from the WordPress database on every load).
- After submission, the customer is shown a confirmation card with their booking details and status.

**What the form collects:**

| Field | Required | Notes |
|-------|----------|-------|
| Firmanavn (Company) | ✅ | Pre-filled from account |
| Kontaktperson | ✅ | Pre-filled from account |
| E-mail | ✅ | Pre-filled from WP account |
| Telefon | ✅ | Pre-filled from account |
| Sælgers kontakt | ❌ | Seller name & phone |
| Ejendomsadresse | ✅ | Property address — never cached |
| Booket af | ✅ | Person making the booking |
| Services | ✅ | Static clickable list (from admin) |
| Dato | ✅ | Min: tomorrow |
| Tidspunkt | ✅ | Time picker |
| Kommentarer | ❌ | Free text |
| Rabatkode | ❌ | WooCommerce coupon |

**After submission:**
1. Booking saved to `wp_zb_bookings` with status `pending`
2. Admin at `booking@homefoto.dk` receives an email with one-click **Bekræft** and **Afvis** links
3. Customer receives a Danish acknowledgement email

---

### `[zb_signup]`

Renders the customer registration form. Creates a WooCommerce `customer` role account.

**Where to place it:** On a page with slug `/opret-konto/`.

**Behaviour:**
- If the visitor is already logged in, they are redirected to `/bookings/` immediately.
- On successful registration, the user is logged in automatically and redirected to `/bookings/`.
- All data is written directly to `wp_usermeta` and WooCommerce billing fields — no sessions, no transients.

**Fields collected:**

| Field | Required | Saved to |
|-------|----------|----------|
| Firmanavn | ✅ | `company_name` + `billing_company` |
| Kontaktperson | ✅ | `contact_person` + `billing_first_name` |
| E-mail | ✅ | WP user email + `billing_email` |
| Telefon | ✅ | `phone` + `billing_phone` |
| Adresse | ✅ | `address` + `billing_address_1` |
| CVR-nummer | ❌ | `cvr` user meta |
| Adgangskode | ✅ | Min. 8 characters |

**Error query strings** (appended to the registration page URL on failure):

| `?zb_error=` | Meaning |
|-------------|---------|
| `email_exists` | E-mail already registered |
| `invalid_email` | Not a valid email format |
| `weak_password` | Password shorter than 8 characters |
| `generic` | WordPress user creation error |

---

## Admin — WordPress Dashboard

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
    ├── db-table.php            ← Database schema (dbDelta)
    ├── registration.php        ← [zb_signup] shortcode + handler
    ├── booking-form.php        ← [zbooking] shortcode + coupon AJAX
    ├── form-handler.php        ← POST handler + email dispatch
    ├── admin-page.php          ← WP Admin menus + email action handler
    └── user-dashboard.php      ← WC My Account tab + AJAX status update
```

---

## Changelog

### v2.4 (2026-04-11)
- Full rename from OSF → Zbooking (zero OSF references remain)
- Renamed `assets/zb-admin.js` (was `osf-admin.js`)
- Added `DONOTCACHEPAGE` / `nocache_headers()` protection
- `wp_cache_delete` before every `get_user_meta` call
- HTML `autocomplete` attributes on all form fields
- WooCommerce HPOS compatibility declared
- Admin one-click Confirm/Reject via `admin-post.php` with nonce
- `booked_by` field added to form, DB, and all email templates
- WC `customer` role assigned on registration
- All billing meta synced to WooCommerce on registration
- `clean_user_cache()` called after meta updates

### v2.3
- Initial refactor from original OSF plugin
- Danish localization of all UI, emails, and admin views
- Two-step booking workflow (pending → Accepted/Rejected)
- Payment-after-photography model (no upfront WC payment)
