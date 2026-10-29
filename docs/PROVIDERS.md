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


## App ownership and authentication

Core never supplies Metribay's provider app credentials. Use your own account API keys, service-account JSON, GitHub personal token or your own GitHub App installation/private key. Resource discovery and collectors remain part of Core. A provider may impose its own limits; Core adds no Metribay service quota.

For Google browser authorization on your **self-hosted server**, register your own Google **Web application** client, enable the Analytics Admin/Data and Search Console APIs, and register the exact callback URL (`http://127.0.0.1:8000/integrations/google/callback` locally). Set `METRIBAY_GOOGLE_CLIENT_ID`, `METRIBAY_GOOGLE_CLIENT_SECRET` and `METRIBAY_GOOGLE_REDIRECT_URI` in the server's protected environment. The UI says “Connect with your Google app”. Service-account and own OAuth credentials also work through the credential form. Google account access to each property is still required. Never ship a confidential Web client secret in a desktop binary; Desktop uses own credentials instead.

Hosts may bind `GoogleAuthorization`, `ConnectionCredentials` and `ProviderRequests` from `Metribay\Core\Contracts`. Defaults use installation-owned Google configuration, decrypt own credentials and permit local provider requests. Runtime collection, discovery and access checks resolve credentials through the host. Every `SafeHttpClient` call invokes the host's request policy, including token exchange and pagination. Policy exceptions fail collection safely without partial metric writes. `ProviderRequestLimit` gives the user a safe retry message.

Managed services store an encrypted reference and user refresh token; the host resolves app secrets server-side and checks access on authorization start, callback and collection. Core rejects payloads containing `managed_app`; changing an edition label cannot unlock the service. Config exports omit **all credentials**, including managed references/tokens. Reconnect with your own account when moving between installations. A host must never expose a credential-resolution endpoint to untrusted clients. Provider apps and public client IDs alone are not a subscription boundary.
