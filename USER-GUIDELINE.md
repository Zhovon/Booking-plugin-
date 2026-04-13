# Zbooking User Guideline

## Quick Start

1. Activate plugin in WordPress.
2. Create 3 pages and add shortcodes:
   - Booking page: `[zbooking]`
   - Login page: `[zb_auth]`
   - Dashboard page: `[zb_dashboard]`
3. Go to **WP Admin → Zbooking → Settings** and set your slugs to match those pages.
4. Go to **WP Admin → Products → Booking Services** and add service items with duration and price.

## Login and Logout Behavior

- The login slug is the main header entry point for auth.
- Logged-out visitors see the login/sign-up form on that slug.
- Logged-in visitors are redirected to the dashboard instead of seeing the login form again.
- The same slug can be used for a header button that switches to Log ud when the user is signed in.

## Admin Setup Checklist

1. Set slugs:
   - Booking slug
   - Login slug
   - Dashboard slug
2. Set slot rules:
   - Slot interval (recommended: 15)
   - Default duration (recommended: 60)
   - Business hours (start/end)
3. Calendar sync setup:
   - Enable Outlook sync and/or Google sync
   - Save the OAuth app credentials in the Advanced App Setup section
   - Register the callback URL shown on the settings page in the provider app
   - Use the connect action on the settings page to authorize the admin calendar
4. Save settings and refresh Permalinks once.

## Pricing Display

- Booking totals now use a normalized currency symbol.
- The plugin no longer prints raw currency HTML entities in the booking form or emails.

## Booking Flow

1. Customer selects services.
2. Customer picks date.
3. Plugin loads only available time slots for that date.
4. Customer submits booking request.
5. Admin confirms or rejects booking.
6. If confirmed and calendar sync is enabled, event is created in the connected Outlook or Google calendar.
7. The admin inbox receives a copy of the booking mail and the calendar attachment.
8. If the booking is rescheduled, both admin and customer receive the updated details.

## Double Booking Prevention

Zbooking prevents collisions in multiple layers:

- Available slot API hides already occupied intervals.
- Server validates date/time and 15-minute boundaries.
- Server checks overlap with existing pending/accepted bookings.
- Server applies DB lock around insert path to reduce race conditions.
- If Outlook is enabled, Outlook calendar conflicts are also checked.

This prevents same-time booking by different users and duplicate booking by the same user.

## WooCommerce Behavior

- Default WooCommerce Add to Cart buttons are hidden.
- A **Book nu** button is shown on product loop and product page.
- Book button points to configured booking slug and passes product id.
- Coupon discount supports WooCommerce coupon types already configured in WC.

## Operational Recommendations

1. Keep slot interval at 15 for predictable scheduling.
2. Keep business hours realistic to avoid empty slot grids.
3. Enable Outlook only after Graph app permissions are fully granted.
4. Review pending bookings daily to avoid stale request queue.
5. Backup database before major plugin updates.

## Customer Dashboard

- Customers can view their bookings from the dashboard page created with the `[zb_dashboard]` shortcode.
- From the booking list, they can request a new time through the reschedule action.
- The admin can then review the request and update the booking date, time, and status.
- Customers can also reschedule from the email links in booking notifications.

## Invoices

- The invoice page is opened from the booking link in the dashboard or email.
- The invoice link is signed so the customer can open it from email without logging in again.

## Troubleshooting

### No available slots are showing

- Check business start/end time in Zbooking settings.
- Ensure service durations are not longer than business window.
- Check if Outlook is enabled and returning all-day busy conflicts.

### Booking submit says slot is unavailable

- Another booking was created between slot load and submit.
- This is expected behavior for conflict safety. Ask user to pick another slot.

### Outlook event not created after acceptance

- Verify Tenant ID, Client ID, Client Secret, Mailbox user.
- Verify Graph permissions and admin consent.
- Keep Outlook enabled in plugin settings.

## Security Notes

- Admin-only actions are capability protected.
- Form and AJAX actions use nonces.
- Output is escaped and input is sanitized.
- Booking and auth pages are marked no-cache.

## Go-Live Checklist

1. Slugs configured and pages published.
2. Booking services configured.
3. Test booking from customer account.
4. Test admin Accept/Reject email flow.
5. Test coupon application.
6. Test Outlook conflict and event creation.
7. Test invoice access permissions.
