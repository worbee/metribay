# Paddle Billing source

Metribay connects to a whole Paddle Billing account. The Revenue section of the project page shows daily net revenue, monthly recurring revenue (MRR) and active paying subscribers. Net revenue is after taxes and Paddle fees, before refunds and chargebacks. MRR and paying subscribers show the latest observation in the selected range. Paddle excludes trials from its active subscriber count. Dates use UTC.

## Approved decisions

- A workspace owner connects Paddle and confirms that project viewers can see the metrics and that workspace exports can include them. An owner must also confirm sharing when importing a configuration that includes a Paddle source.
- The source uses a separate API key with only `metrics.read`. Metribay verifies its permission with Paddle before storing it and before every collection. The key is encrypted in the workspace. Collection checks the account ID against the one chosen in setup, so replacing a key with one from a different account cannot mix data.
- The collector calls the Paddle API root for permission and account identity, then the three fixed metrics endpoints. The response data used for charts and exports consists of dates, amounts, currencies and counts. Customer IDs, names, emails, payment records and subscription records stay at Paddle. Each endpoint uses a fixed Paddle host. The analytics connection is independent of any checkout or billing integration.
- Money is reported in Paddle's balance currency. Currency travels with each observation and is included in history CSV exports. The dashboard does not add revenue across currencies or compare different currencies. Dates without provider data remain gaps.
- Product filters and churn need separate validation before they ship. The fixed subscriber series is account-wide. Paddle reports MRR lost to churn separately from the number of subscribers lost. Hosted Stripe collection must meet the same customer-data boundary and remains a separate opt-in pilot.
- Publish a localized integration page and setup guide when each provider ships. Explain the key's permissions and where customer records stay. Verify a closed month against a live Paddle account using a dedicated read-only key before release.

See [Paddle's Metrics API](https://developer.paddle.com/api-reference/metrics/) and [API key permissions](https://developer.paddle.com/api-reference/about/permissions/).
