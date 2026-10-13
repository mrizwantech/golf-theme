# Mobile passkeys

This adds passkey enrollment, sign-in, listing, and removal for existing mobile
accounts. Password and Apple sign-in are unchanged. It does not add passkey
buttons to the WordPress website.

## Deploy

1. Upload `tee-time-nexus-bookings.php`, `inc/class-ttn-passkey-auth.php`,
   `composer.json`, `composer.lock`, and the **complete `vendor/` directory**
   together to the active bookings plugin. These includes/dependencies are
   currently git-ignored in this website checkout; do not assume a Git deploy
   includes them.
2. Use PHP 8.0+ with OpenSSL and mbstring. To build the production dependencies,
   run `composer install --no-dev --prefer-dist --no-interaction` in this plugin.
   Do not upload private keys. Passkey routes initialize two prefixed database
   tables with WordPress `dbDelta`; the database user needs CREATE/ALTER rights.
   Ordinary password/Apple authentication does not load the passkey verifier.
3. Confirm the following URLs return **HTTP 200 JSON without redirects**:
   - `https://teetimenexus.com/.well-known/apple-app-site-association`
   - `https://teetimenexus.com/.well-known/assetlinks.json`
   The plugin handles these requests before canonical redirects if the host
   forwards them to WordPress. If the host blocks dot-directories or bypasses
   WordPress, configure the host to forward these two paths or publish equivalent
   static JSON at the website document root. Existing static files take
   precedence; merge any existing associations instead of overwriting them.
4. The iOS webcredentials association is
   `437X3V3YF8.com.teetimenexus.app`. `TTN_PASSKEY_APPLE_TEAM_ID` in `wp-config.php`
   can override the Team ID. The mobile app has the matching Associated Domains
   entitlement, `webcredentials:teetimenexus.com`. Enable Associated Domains for
   this App ID in Apple Developer and regenerate the signing provisioning
   profile. Verify the signed application's application-identifier prefix
   matches the association, especially if the app was transferred between teams.
5. Android is **disabled until actual signing fingerprints are provided**.
   Add this to `wp-config.php`, using real colon-separated SHA-256 fingerprints:

   ```php
   define('TTN_PASSKEY_ANDROID_SHA256', array(
       // Play Console > App integrity > App signing key certificate > SHA-256.
       // Also include the EAS signing certificate for directly installed APKs
       // if those builds need passkeys. Do not use the Play upload certificate
       // instead of the Play App Signing certificate for Play-installed builds.
   ));
   ```

   An empty array keeps Android disabled. The plugin derives both the
   `delegate_permission/common.get_login_creds` association and the exact
   `android:apk-key-hash:` allowlist from the same fingerprints. If serving a
   static assetlinks file, synchronize it with these fingerprints.
6. Purge association caches after changing configuration. Apple and Android can
   cache associations outside the website, so allow time for propagation and
   test using the intended signed build, not just a browser request.
7. Rebuild the Expo application with `react-native-passkey`. For generated
   native projects, regenerate with Expo prebuild instead of editing
   entitlements manually. An OTA update or Metro reload alone is insufficient.

Example Apple association:

```json
{"webcredentials":{"apps":["437X3V3YF8.com.teetimenexus.app"]}}
```

## Use and verification

After password/Apple sign-in or account creation, customers with no registered
passkeys get a one-time **Enable / Not now** explanation on that device/account.
Only Enable opens native enrollment. The choice is remembered locally; session
restoration never triggers the offer. **Profile > Settings > Manage Passkeys**
remains available for adding/removing keys later. Sign out and expand
**Additional sign-in options**, then tap **Sign in with a passkey**.
The app never opens a native passkey prompt automatically. Web, Expo Go, older binaries, unsupported devices,
and missing server configuration show explicit errors; fallback sign-in remains.

On physical iOS and Android devices, verify enrollment, sign-out/sign-in,
cancellation, no available key, multiple keys, removal, password and Apple
fallbacks, and a new device with a synced key. Confirm an account cannot remove
another account's key. Removing a key blocks future sign-ins with that key;
it does not sign out existing sessions or delete the entry from Apple
Passwords/Google Password Manager.

## Protocol

Authenticated enrollment requires a resident platform credential and user
verification. Anonymous login uses discoverable credentials. The server checks
challenge, ceremony type, user handle, exact origin, RP hash, user presence,
verification, and ES256 signatures using pinned `lbuchs/webauthn` 2.2.0.
Challenges expire after two minutes and are consumed once by an atomic delete.
Credential IDs are globally unique; each account has up to ten unique slots.
Options requests have a separate 30-per-minute limit for each client IP and
login/enrollment scope. Opening or cancelling a native prompt does not count as
a failed verification. Only invalid verification responses count toward the
five-failure/15-minute lock through existing JWT rate helpers; successful
verification clears that scope's failures. Enrollment is scoped to the signed-in
account, independently of anonymous login. Setup/server errors do not count as
failed verifications. Legacy setup lockouts are not used by the new limits.
Authentication options and account records must not be CDN-cached.

Public keys, opaque account handles, names, and counters are stored, not device
biometrics or private keys. Device-bound counters must increase when nonzero;
backup-eligible synced passkeys may have zero/non-monotonic counters. Backup
eligibility cannot change. Public credentials are returned only as names and
management identifiers. HTTPS RP/origin is intentionally fixed to
`teetimenexus.com`; staging requires a separately configured association,
app entitlement, RP/origin implementation, and separate credentials.

Run from the theme directory:

```sh
php tests/mobile-passkeys.php
php -d disable_functions=openssl_verify tests/mobile-passkeys.php --dependencies-missing
```

The first test exercises the actual verifier using generated ES256 signatures
and CBOR attestations, with an in-memory SQLite WordPress database adapter.
It does not replace a WordPress/MySQL deployment smoke test or native device
association verification.
