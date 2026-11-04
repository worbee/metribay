# Optional usage analytics

Metribay includes CookieConsent v3 and an optional Umami integration. These are distinct from connected analytics providers and the metrics imported into projects.

## What happens

No script or measurement request goes to the analytics host before explicit consent. The initial dialog offers equally weighted Accept analytics and Reject analytics, plus Preferences. Necessary preferences are separate and cannot be disabled. Privacy settings in the footer reopens the choice. Declining does not restrict application features.

Cloud shares the `cc_cookie` preference with metribay.com on the `metribay.com` domain, revision 1, for 182 days, SameSite Lax and Secure on HTTPS. Authentication cookies are unaffected. Personal and Desktop each use a separate host-only preference (`metribay_personal_usage` / `metribay_desktop_usage`), and ask whether the user wants to help improve Metribay. Preferences belong to the browser/WebView profile, not the account, and are not synced across devices. Clearing cookies asks again.

Withdrawing analytics reloads an active tracked page. Every outgoing event is also checked against the current cookie, so another tab's withdrawal takes effect before further tracking even before focus returns. Script-load callbacks recheck consent. A request already sent with consent cannot be recalled. Blocked analytics scripts leave the app usable.

## Collection boundary

The bundled code loads `https://s.worbee.com/script.js` only on allowed page routes after consent. It sends explicit payloads to Umami website `e2ab0564-e9ab-4a86-b4cc-12bb0c8369f5`; automatic tracking is disabled. The hosted tracker was checked for support for manual object payloads and the before-send hook on 2026-11-03.

App URLs become coarse categories such as `/cloud/project`, `/personal/settings` or `/desktop/overview`. Titles are generic. Personal/Desktop send a synthetic hostname in the event payload rather than a local hostname. No user/workspace/project identifiers, names, emails, real titles, original URLs, query parameters, referrers, provider configuration, credentials, screen recordings or distinct user identifiers are sent. An allowlist in `Services/UsageAnalytics.php` excludes operator pages, OAuth callbacks, password-reset and confirmation-token screens. Only allowed GET page renders initialize measurement; Livewire background refreshes do not count as page views.

Selected Cloud conversion markers (`signup_submitted`, `signup_completed`) may be provided through a flashed `analytics_event` session key. They are sent once on the landing page only if consent exists then or is given on that page. They are not queued for later visits. Unknown markers are ignored. Clicks are not treated as successful signups. First import/connection completion and retention are not instrumented by this integration.

The analytics server necessarily receives the connection IP address. Browser-managed network headers can also reveal the requesting origin (including a self-hosted hostname or Desktop loopback origin); the synthetic payload hostname does not hide those transport headers. Umami may derive anonymous session identifiers from IP/browser data; this is not a promise of absolute anonymity. The app does not send a persistent account or installation identifier. The website and all editions currently use the supplied website ID, with edition/path categories for filtering. This is approximate audience measurement, not reliable cross-device attribution.

## Operator controls

Without `METRIBAY_ANALYTICS_ENABLED`, the default follows `metribay.edition`: off in the self-hosted `personal` edition (no prompt, no Privacy settings link, no script), on in `cloud` and `desktop`. `true` or `false` overrides it in any edition; rebuild the Laravel config cache if used. `metribay.analytics.script_url` and `website_id` are configurable in the host's `config/metribay.php`. No provider credentials or user data are required to operate analytics.

Desktop must set `metribay.edition=desktop`; the shared Core UI then supplies the independent opt-in. Its WebView cookie storage must persist the user's choice. Desktop does not collect background/startup metrics or automatically upload crash reports. Self-hosted operators remain responsible for accurately informing their users; changes to purposes or providers should bump the consent revision consistently in website and Cloud and update disclosures.

CookieConsent JS/CSS are bundled locally with third-party licenses. The consent choice is stored locally, not in a separate consent audit service. Production transport, Umami access restrictions, retention and processing arrangements must be maintained by the analytics operator; the banner does not establish a legal basis by itself.

## Checks

`npm test` covers missing/invalid/old/declined consent, allowlisted payloads, Personal/Desktop isolation, acceptance, duplicate loads, revocation while loading, retry and rejection of identification requests. `tests/Feature/UsageAnalyticsTest.php` covers safe markup, sensitive route exclusion, global disable and conversion marker filtering. Browser acceptance also checks that no analytics host request occurs before consent, acceptance works, and withdrawal stops subsequent calls.
