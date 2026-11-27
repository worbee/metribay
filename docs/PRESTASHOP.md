# PrestaShop source

**PrestaShop** (`prestashop`) reads one shop of a PrestaShop 1.7, 8 or 9 installation through its built-in Webservice, read-only and in JSON. The Revenue section of the project page shows **Store revenue** and **Orders** per day.

## What is counted

- **Orders** (`store_orders`): orders the shop marks as valid (`valid = 1`), by the day they were placed (`date_add`). PrestaShop sets `valid` when an order reaches a state it counts as validated, such as payment accepted, and clears it when the order is cancelled. Its own statistics use the same rule.
- **Store revenue** (`store_revenue`): `total_paid_tax_excl` of those orders, divided by the exchange rate stored on each order (`conversion_rate`). That is the order total without tax, including shipping and after discounts, in the shop's default currency. The currency travels with every value and appears in history exports.
- Refunds and credit slips are not subtracted. A cancellation after the revision overlap (`metribay.collection.revision_overlap_days`, 7 days) is not seen until a full re-import.
- Days without valid orders are recorded as zero. Today is partial.
- Average order value and new customers are not collected yet. Customer counts would need the key to read customer records.

## Setup

- The connected account is the shop's public HTTPS address and a webservice key. `/api` and a trailing slash are removed when saving, so either form works. The key must be 32 characters and is sent only as the HTTP Basic user name (empty password), never in the URL.
- The key needs **View (GET)** on `orders`, `currencies` and `shops` only. It does not need `customers` or `configurations`; the configuration table can hold other modules' secrets.
- The webservice needs friendly URLs (URL rewriting) so `/api` resolves. Requests go through `SafeHttpClient`: public HTTPS on port 443, no redirects. A shop on a private network, behind a login wall or with a redirect from the entered address fails with a safe message.
- Discovery lists active shops from `GET /api/shops`. A single shop without multistore has ID 1, so manual entry works when the key may not read `shops`. Every request sends `id_shop`, so a multistore key needs access to that shop.
- PrestaShop stores order dates in the shop's own timezone (**International → Localization → Configuration**) and the Webservice does not expose it without configuration access. The person adding the source enters it on the confirm step (or with manual entry), and the source's reporting timezone must match it. A mismatch shifts orders between days; the connector cannot detect it.

## Collection

Each run reads the currencies (`display=[id,iso_code,conversion_rate,active,deleted]`) and takes the default currency as the one active currency at rate 1. Deleted or inactive currencies count only when no active one has rate 1. If that is not exactly one currency, the run fails rather than guess.

Orders are read with `filter[id_shop]`, `filter[valid]=[1]`, `filter[date_add]=[{first} 00:00:00,{last} 23:59:59]` and `date=1` (PrestaShop ignores date filters without it), sorted by ID, 1,000 per page and at most 400 pages. A shorter history window is the answer for a larger shop. An order outside the window, a malformed date or amount, or a rate of zero fails the run without writing anything.

## Access

PrestaShop is a revenue provider (`ProviderCatalog::REVENUE_PROVIDERS`), like Paddle: only people who can manage workspace access add, change or import it, adding one requires confirming that project viewers see the figures and exports include them, and the generic data source form refuses it.

See the [PrestaShop Webservice documentation](https://devdocs.prestashop-project.org/9/webservice/).
