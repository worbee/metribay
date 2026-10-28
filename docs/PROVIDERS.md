# Provider connections

| Provider | Resource configuration | Connection |
|---|---|---|
| Umami | website_id,days; UTC compatibility mode | public HTTPS base_url,username,password |
| GA4 | property_id,days; exact property timezone | service_account JSON, or client_id/client_secret/refresh_token |
| Search Console | site_url,days; America/Los_Angeles | service_account JSON, or OAuth refresh credentials |
| Bing | site_url,days; UTC | api_key |
| WordPress.org | slug,limit; UTC | none |
| GitHub | owner/repository,days; UTC | token, or client_id/installation_id/private_key |
| UptimeRobot | monitor_id; UTC observations | read-only api_key |

Dates and numeric rows are strictly validated. Umami verifies site access before trusting zero-looking stats and independently queries visits/period visitors. GA4 validates metadata and timezone, marks recent dates provisional and rejects sampled, thresholded or restricted reports, and separately requests exact user totals. Search Console combines final/all data states with pagination and explicit finality. Bing decodes .NET dates and keeps traffic/crawl gaps independent. WordPress verifies plugin existence before trusting download counts. GitHub bounds cursor pagination, separates repository activity from productivity claims, and reconciles release annotations. UptimeRobot validates monitor identity, exact rolling24h windows, response-time sample availability and a shared per-credential request limit.

Workspace connections accept key material, not arbitrary server filesystem paths. Add source guides account connection, resource discovery and project confirmation. Google browser OAuth appears when the host configures metribay.google client credentials; its shared callback is /integrations/google/callback. Advanced refresh credentials and manual resource entry remain available. GitHub App resource discovery currently requires manual repository entry. Provider errors never expose bodies or credentials. A successful API call verifies that the supplied credential can reach the resource; the hosted service does not silently grant access through a shared operator identity.

Fixture tests exercise all providers offline. A real public WordPress.org smoke passed at initial setup; that does not establish authenticated permissions for the other providers.
