=== Tee Time Nexus Competitions ===
Contributors: teetimenexus
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later

Manage and publish golf league and tournament listings for the Tee Time Nexus website and mobile app.

== Installation ==

1. Copy `tee-time-nexus-competitions` into `wp-content/plugins/`.
2. Activate **Tee Time Nexus Competitions** in WordPress.
3. Open **Golf Competitions** in the WordPress dashboard and choose **Create Missing Listing Pages**.
4. Add the generated listing pages to the site's primary navigation under Appearance → Menus.
5. Create a competition, select League or Tournament, and publish it.
6. For paid events, verify WooCommerce checkout and payment methods in test mode before accepting live registrations.

To update an existing installation, back up the site, replace the plugin files with the latest version, and load the WordPress dashboard. The versioned registration table is migrated automatically; competition posts are preserved.

The plugin does not edit existing pages or add navigation items automatically. Competition data remains on deactivation and uninstall.

Registration payment products are published but hidden from the store catalog, matching the booking plugin's product setup. They can only be added through registration. Version 1.1.3 validates saved registration cart items before WooCommerce restores them and explicitly saves the payment session before redirecting. Existing private registration products are updated when payment is started or resumed.

Run the standalone cart regression checks with `php tests/registration-cart-test.php` from the plugin directory. These checks use WordPress/WooCommerce test doubles; test an actual checkout in WooCommerce test mode before accepting live payments.

== Competition fields ==

Competition editors provide event content and featured images plus dates, registration dates, entry fee, capacity, format, and course. Leave the fee blank if it has not been set; enter 0 for explicitly free entry. A 0 capacity means no limit is stated.

Signed-in WordPress users can register for events. Paid entry uses the existing WooCommerce checkout and payment methods, and free entry confirms directly. Starting a competition payment replaces the existing cart with the registration so unrelated items cannot change the checkout total. WooCommerce coupons apply to the registration product. Registration capacity counts confirmed players and temporary pending-payment holds; abandoned holds expire based on WooCommerce's Hold Stock setting (30 minutes if that setting is disabled). Failed, cancelled, and refunded WooCommerce orders release the place. A late successful payment after its place has been released is automatically refunded when the gateway supports refunds; review any order note if the refund fails.

The plugin records player name/email from the signed-in account, phone, optional handicap and GOLFZON username, accepted rules timestamp, registration status, fee, and order ID. Administrators can view the latest 200 entries under Golf Competitions → Registrations and manage refunds in WooCommerce orders.

Competition registration fields are included in WordPress personal data exports and are anonymized by personal data erasure requests. WooCommerce order data is handled by WooCommerce's own privacy tools.

This release supports one individual player per account per competition. It does not provide team registration, scheduled matches, score entry, leaderboards, reminders, member discounts, or bay-booking integration. Verify that WooCommerce checkout and at least one payment method are enabled before publishing paid registration.

== Public API ==

`GET /wp-json/ttn/v1/competitions`

Optional query parameters:

* `type=league` or `type=tournament` (default `all`)
* `limit=1` through `50` (default `30`)

Response:

```json
{
  "items": [
    {
      "id": 123,
      "title": "Spring League",
      "type": "league",
      "description": "League details",
      "start_date": "2027-03-01",
      "end_date": "2027-04-30",
      "registration_start": "2027-02-01",
      "registration_end": "2027-02-25",
      "entry_fee": 75,
      "currency": "USD",
      "capacity": 24,
      "format": "Individual Stroke Play",
      "course": "Example Course",
      "image_url": "https://example.com/image.jpg",
      "url": "https://example.com/competition/spring-league/"
    }
  ],
  "count": 1
}
```

Only published competitions with a start date on or after the site's current date are returned, ordered by start date. The endpoint is read-only and public.

== Website shortcodes ==

* `[ttn_competitions view="landing"]` — landing page and upcoming events
* `[ttn_competitions type="league"]` — league listing
* `[ttn_competitions type="tournament"]` — tournament listing

== Development phases ==

Version 1.0.0 established listings, event editing, and the public mobile feed. Version 1.1.0 adds individual registration, WooCommerce checkout, and registration administration. Version 1.1.1 isolates competition checkout from any previous cart items. Version 1.1.2 lets players resume payment when a pending registration has no linked order and releases expired orphaned holds. Teams, scheduled matches, score entry, leaderboards, membership discounts, and bay-booking integration remain future phases and must be implemented and tested before being advertised as available.
