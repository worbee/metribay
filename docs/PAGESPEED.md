# Page speed

Add **Page speed** from a project. Enter a public HTTPS page URL, keep **Mobile** or select **Desktop**, then confirm. Each device is a separate source, so mobile and desktop results cannot overwrite each other. Private pages and pages requiring sign-in cannot be tested.

Personal/standalone installations connect their own Google API key first. Enable **PageSpeed Insights API** and **Chrome UX Report API** for that key's Google Cloud project. This is API-key access to public data; no Google sign-in or OAuth consent is required. Cloud hosts can bind `PageSpeedCredentials` to supply a managed key without saving it in users' connections. Metribay's managed key is never included in Core or Desktop.

## Lab tests

Daily Lighthouse snapshots record performance score (0–100), Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS), and Total Blocking Time (TBT). Each observation retains its test time, requested/final URLs, device and Lighthouse version. Repeated requests for the same URL/device/key on the same UTC day reuse a cached lab result; a shared lock prevents duplicate concurrent tests. The provider timeout is bounded at 180 seconds.

History begins with the first test. A current score cannot reconstruct earlier lab results. Charts retain missing dates as gaps. Snapshot values use the latest observation in the selected period, without generic growth percentages. The overview shows the **lowest** score across a project's tracked pages/devices and defaults to lowest-first sorting; speed scores are excluded from totals across projects.

## Real users

CrUX supplies p75 LCP, INP and CLS for rolling 28-day collection periods. The API is independent of PageSpeed Insights, whose embedded field data is being retired by Google. Query strings are removed for CrUX page lookups, while lab tests retain the full requested URL. A missing page record falls back to its origin. Page and whole-site series have separate metric keys and sections; they are never joined or added together. Origins retain their exact scheme/hostname identity.

The current CrUX record is fetched once per UTC day. Available history is seeded from up to 40 weekly collection periods, refreshed once per ISO week. History dates use the actual collection-period end, not the time of import; each point retains its exact bounds. Missing history samples remain unknown. Revised windows replace earlier values, including removing values that the provider no longer reports.

Google may have no eligible data for a page or even its whole site. This is **Not enough real-user data**, not zero and not a failed lab test. A CrUX permission, quota or service problem displays a separate refresh warning while retaining the lab result and existing field history. Historical coverage is never fabricated.

## Operations

Normal scheduled imports run page-speed sources at most once every 24 hours after a successful collection. Explicit sync/check and weekly full refreshes can still run, subject to the same caches and request policy. Repeated tests do not bypass the daily lab cache. Core limits combined requests to 100 per minute per API key, below the shared CrUX/History quota of 150/minute. Cloud adds its own owner/application budgets. Every HTTP request also passes the host's `ProviderRequests` policy.

`daily_metrics.context` is nullable JSON with a small allowlist of measurement metadata; it never stores the API key or raw provider responses. The new migration is additive. A host rollback can retain the column, but pause any Page speed sources before running an older Core that does not support the provider.

Fixture tests use synthetic URLs, credentials and API responses. Live collection still requires a valid key with both APIs enabled.

References: [PageSpeed API](https://developers.google.com/speed/docs/insights/v5/get-started), [CrUX API](https://developer.chrome.com/docs/crux/api/), [CrUX History](https://developer.chrome.com/docs/crux/history-api/).
