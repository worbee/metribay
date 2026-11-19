<?php

namespace Metribay\Core\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\AzureDevOpsConnector;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ContentUrl;
use Metribay\Core\Connectors\PaddleConnector;
use Metribay\Core\Connectors\PageSpeedUrl;
use Metribay\Core\Connectors\PostHogClient;
use Metribay\Core\Connectors\PostHogConnector;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SocialAccount;
use Metribay\Core\Contracts\PageSpeedCredentials;
use Metribay\Core\Metrics\MetricRegistry;

class ProviderCatalog
{
    /** @return array<string, array{label: string, fields: list<string>, secrets: list<string>, config: list<string>, default_timezone: string, field_labels: array<string, string>}> */
    public static function all(): array
    {
        $data = [
            'umami' => ['Umami', ['base_url', 'username', 'password'], ['password'], ['website_id', 'days'], 'UTC'],
            'plausible' => ['Plausible', ['base_url', 'api_key'], ['api_key'], ['site_id', 'days'], 'UTC'],
            'matomo' => ['Matomo', ['base_url', 'token_auth'], ['token_auth'], ['site_id', 'days'], 'UTC'],
            'posthog' => ['PostHog', ['base_url', 'api_key'], ['api_key'], ['project_id', 'event', 'days'], 'UTC'],
            'ga4' => ['Google Analytics 4', ['service_account', 'client_id', 'client_secret', 'refresh_token'], ['service_account', 'client_secret', 'refresh_token'], ['property_id', 'days'], 'UTC'],
            'gsc' => ['Google Search Console', ['service_account', 'client_id', 'client_secret', 'refresh_token'], ['service_account', 'client_secret', 'refresh_token'], ['site_url', 'days'], 'America/Los_Angeles'],
            'bing' => ['Bing Webmaster Tools', ['api_key'], ['api_key'], ['site_url', 'days'], 'UTC'],
            'pagespeed' => ['Page speed', ['api_key'], ['api_key'], ['url', 'strategy'], 'UTC'],
            'wporg' => ['WordPress.org', [], [], ['slug', 'limit'], 'UTC'],
            'github' => ['GitHub', ['token', 'client_id', 'installation_id', 'private_key'], ['token', 'private_key'], ['repository', 'days', 'environments', 'convert_to_events'], 'UTC'],
            'github_releases' => ['GitHub releases', ['token'], ['token'], ['repository'], 'UTC'],
            'feed' => ['RSS / Atom', [], [], ['url', 'days', 'convert_to_events'], 'UTC'],
            'sitemap' => ['Sitemap', [], [], ['url', 'days', 'convert_to_events'], 'UTC'],
            'uptimerobot' => ['UptimeRobot', ['api_key'], ['api_key'], ['monitor_id'], 'UTC'],
            'paddle' => ['Paddle Billing', ['api_key'], ['api_key'], ['paddle_account', 'days'], 'UTC'],
            'azure_devops' => ['Azure DevOps', ['organization', 'token'], ['token'], ['repository', 'days', 'environments'], 'UTC'],
            'bluesky' => ['Bluesky', [], [], ['account'], 'UTC'],
            'mastodon' => ['Mastodon', [], [], ['account'], 'UTC'],
        ];
        $out = [];
        foreach ($data as $kind => [$label,$fields,$secrets,$config,$timezone]) {
            $out[$kind] = ['label' => $label, 'fields' => $fields, 'secrets' => $secrets, 'config' => $config, 'default_timezone' => $timezone, 'field_labels' => ['website_id' => 'Website UUID', 'base_url' => 'Server URL', 'service_account' => 'Service account JSON', 'site_url' => 'Site URL or Search Console property', 'private_key' => 'GitHub App private key (PEM)', 'token' => 'Personal access token', 'organization' => 'Organization', 'api_key' => 'Api Key', 'token_auth' => 'Auth token', 'project_id' => 'Project ID', 'event' => 'Event name', 'days' => 'History window (days)', 'limit' => 'History window (days)']];
        }

        return $out;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function source(string $kind, array $input, string $timezone): array
    {
        $provider = self::all()[$kind] ?? null;
        if (! $provider) {
            throw ValidationException::withMessages(['kind' => __('Choose a supported provider.')]);
        }
        if (array_diff(array_keys($input), $provider['config'])) {
            throw ValidationException::withMessages(['config' => __('Unsupported data source setting.')]);
        }
        Validator::make(['timezone' => $timezone], ['timezone' => ['required', 'timezone']])->validate();
        if (in_array($kind, ['umami', 'bing', 'github', 'github_releases', 'feed', 'sitemap', 'uptimerobot', 'pagespeed', 'azure_devops', 'paddle', 'bluesky', 'mastodon'], true) && ! in_array($timezone, ['UTC', 'Etc/UTC', 'Etc/GMT', 'GMT'], true)) {
            throw ValidationException::withMessages(['reporting_timezone' => __('This provider reports calendar dates in UTC.')]);
        }
        if ($kind === 'gsc' && $timezone !== 'America/Los_Angeles') {
            throw ValidationException::withMessages(['reporting_timezone' => __('Search Console reports dates in America/Los_Angeles.')]);
        }
        $rules = match ($kind) {
            'umami' => ['website_id' => ['required', 'uuid']],
            'plausible' => ['site_id' => ['required', 'string', 'max:253', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/i']],
            'matomo' => ['site_id' => ['required', 'regex:/^[1-9][0-9]{0,9}$/']],
            'posthog' => ['project_id' => ['required', 'regex:/^[1-9][0-9]{0,18}$/'], 'event' => ['sometimes', 'nullable', 'string', 'max:500']],
            'ga4' => ['property_id' => ['required', 'regex:/^[0-9]+$/']],
            'gsc' => ['site_url' => ['required', 'string', 'max:500']],
            'bing' => ['site_url' => ['required', 'url:http,https', 'max:500']],
            'wporg' => ['slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'max:100']],
            'github' => ['repository' => ['required', 'regex:~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', 'max:240'],
                'environments' => ['sometimes', 'array', 'list', 'max:'.self::MAX_ENVIRONMENTS], 'environments.*' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F,]+$/u']],
            'github_releases' => ['repository' => ['required', 'regex:~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', 'max:240']],
            'feed' => ['url' => ['required', 'string', 'max:500']],
            'sitemap' => ['url' => ['required', 'string', 'max:500']],
            'uptimerobot' => ['monitor_id' => ['required', 'regex:/^[0-9]+$/']],
            'azure_devops' => ['repository' => ['required', 'string', 'max:129', 'regex:~^[^/\\\\\x00-\x1F\x7F]{1,64}/[^/\\\\\x00-\x1F\x7F]{1,64}$~uD'],
                'environments' => ['sometimes', 'array', 'list', 'max:'.self::MAX_ENVIRONMENTS], 'environments.*' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F,]+$/u']],
            'paddle' => ['paddle_account' => ['required', 'regex:'.PaddleConnector::ACCOUNT_PATTERN]],
            'pagespeed' => ['url' => ['required', 'string', 'max:500'], 'strategy' => ['required', 'in:mobile,desktop']],
            'bluesky', 'mastodon' => ['account' => ['required', 'string', 'max:320']],
            default => throw ValidationException::withMessages(['kind' => __('Choose a supported provider.')]),
        };
        if ($kind === 'pagespeed') {
            $input['strategy'] ??= 'mobile';
            if (is_string($input['url'] ?? null)) {
                try {
                    $input['url'] = PageSpeedUrl::normalize($input['url']);
                } catch (ConnectorException $exception) {
                    throw ValidationException::withMessages(['config.url' => __($exception->getMessage())]);
                }
            }
        }
        $window = $kind === 'wporg' ? 'limit' : 'days';
        if (in_array($window, $provider['config'], true)) {
            $input[$window] = $input[$window] ?? 180;
            $rules[$window] = ['required', 'integer', 'min:1', 'max:366'];
        }
        if (self::convertsValuesToEvents($kind)) {
            $rules['convert_to_events'] = ['sometimes', 'boolean'];
        }
        $clean = Validator::make($input, $rules)->validate();
        if (array_key_exists('convert_to_events', $clean)) {
            $clean['convert_to_events'] = filter_var($clean['convert_to_events'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($clean[$window])) {
            $clean[$window] = (int) $clean[$window];
        }
        foreach (['property_id', 'monitor_id'] as $numericId) {
            if (isset($clean[$numericId])) {
                $clean[$numericId] = (string) $clean[$numericId];
            }
        }
        if ($kind === 'gsc' && ! preg_match('~^(sc-domain:[a-z0-9.-]+|https?://[^\s]+)$~i', $clean['site_url'])) {
            throw ValidationException::withMessages(['config.site_url' => __('Use a Search Console URL or sc-domain: property.')]);
        }
        if (in_array($kind, ['github', 'github_releases'], true) && array_intersect(explode('/', $clean['repository']), ['.', '..'])) {
            throw ValidationException::withMessages(['config.repository' => __('Use owner/repository.')]);
        }
        if ($kind === 'azure_devops') {
            [$project, $repository] = array_map(trim(...), explode('/', $clean['repository']));
            if ($project === '' || $repository === '') {
                throw ValidationException::withMessages(['config.repository' => __('Use project/repository.')]);
            }
            $clean['repository'] = $project.'/'.$repository;
        }
        if (self::hasDeploymentEnvironments($kind) && isset($clean['environments'])) {
            $clean['environments'] = self::environments(implode(',', $clean['environments']));
            if ($clean['environments'] === []) {
                unset($clean['environments']);
            }
        }

        if ($kind === 'plausible') {
            $clean['site_id'] = strtolower($clean['site_id']);
        }
        if ($kind === 'posthog') {
            $clean['project_id'] = (string) $clean['project_id'];
            if (($clean['event'] ?? null) === null || trim((string) $clean['event']) === '') {
                unset($clean['event']);
            } else {
                try {
                    $clean['event'] = PostHogConnector::event($clean['event']);
                } catch (ConnectorException $exception) {
                    throw ValidationException::withMessages(['config.event' => __($exception->getMessage())]);
                }
            }
        }
        if ($kind === 'feed' || $kind === 'sitemap') {
            try {
                $clean['url'] = ContentUrl::normalize($clean['url']);
            } catch (ConnectorException $exception) {
                throw ValidationException::withMessages(['config.url' => __($exception->getMessage())]);
            }
        }
        if ($kind === 'bluesky' || $kind === 'mastodon') {
            $clean['account'] = self::socialAccount($kind, $clean['account']);
        }

        return $clean;
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function credentials(string $kind, array $values): array
    {
        $provider = self::all()[$kind] ?? null;
        if (! $provider || ! $provider['fields']) {
            throw ValidationException::withMessages(['kind' => __('This provider does not use a connected account.')]);
        }
        if (array_diff(array_keys($values), $provider['fields'])) {
            throw ValidationException::withMessages(['values' => __('Unsupported account field.')]);
        }
        $values = array_filter($values, fn ($value) => $value !== null && $value !== '');
        foreach ($values as $key => $value) {
            if (! is_string($value) && ! ($key === 'service_account' && is_array($value))) {
                throw ValidationException::withMessages(['values' => __('Credentials must be text or service account JSON.')]);
            }if (is_string($value) && strlen($value) > 64000) {
                throw ValidationException::withMessages(['values' => __('An account value is too long.')]);
            }
        }
        $required = match ($kind) {
            'umami' => ['base_url', 'username', 'password'],'plausible','posthog' => ['base_url', 'api_key'],'matomo' => ['base_url', 'token_auth'],'azure_devops' => ['organization', 'token'],'bing','uptimerobot','pagespeed','paddle' => ['api_key'],'github' => isset($values['token']) ? ['token'] : ['client_id', 'installation_id', 'private_key'],'github_releases' => ['token'],'gsc','ga4' => isset($values['service_account']) ? ['service_account'] : ['client_id', 'client_secret', 'refresh_token'],default => throw ValidationException::withMessages(['kind' => __('Choose a supported provider.')])
        };
        foreach ($required as $key) {
            if (empty($values[$key])) {
                throw ValidationException::withMessages(['values.'.$key => 'Complete the required connection fields.']);
            }
        }
        if ($kind === 'github_releases') {
            $values['token'] = trim($values['token']);
        }
        if ($kind === 'azure_devops') {
            try {
                $values['organization'] = AzureDevOpsConnector::organization($values['organization']);
            } catch (ConnectorException) {
                throw ValidationException::withMessages(['values.organization' => __('Use your Azure DevOps organization name, as in dev.azure.com/organization.')]);
            }
            $values['token'] = trim($values['token']);
        }
        if ($kind === 'paddle') {
            $values['api_key'] = trim($values['api_key']);
            try {
                app(PaddleConnector::class)->accountId($values);
            } catch (ProviderRequestLimit $exception) {
                throw ValidationException::withMessages(['values.api_key' => $exception->getMessage()]);
            } catch (ConnectorException) {
                throw ValidationException::withMessages(['values.api_key' => __('Use a valid Paddle API key with only Metrics (Read).')]);
            }
        }
        if ($kind === 'pagespeed') {
            Validator::make($values, ['api_key' => ['required', 'string', 'max:200']])->validate();
            $values['api_key'] = trim($values['api_key']);
        }
        if (isset($values['service_account'])) {
            try {
                $account = is_array($values['service_account']) ? $values['service_account'] : json_decode($values['service_account'], true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($account) || empty($account['client_email']) || empty($account['private_key']) || ! openssl_pkey_get_private($account['private_key'])) {
                    throw new \RuntimeException;
                }$values['service_account'] = $account;
            } catch (\Throwable) {
                throw ValidationException::withMessages(['values.service_account' => __('Provide a valid service account JSON key.')]);
            }
        }
        if (in_array($kind, ['plausible', 'matomo'], true)) {
            $values['base_url'] = rtrim(trim($values['base_url']), '/');
            $secret = $kind === 'plausible' ? 'api_key' : 'token_auth';
            $values[$secret] = trim($values[$secret]);
            if (str_contains((string) (parse_url($values['base_url'], PHP_URL_PATH) ?? ''), '..')) {
                throw ValidationException::withMessages(['values.base_url' => __('Use a public HTTPS server URL on port 443.')]);
            }
        }
        if ($kind === 'posthog') {
            $values['api_key'] = trim($values['api_key']);
            if (str_starts_with($values['api_key'], 'phc_')) {
                throw ValidationException::withMessages(['values.api_key' => __('This is a project API key, which can only send events. Create a personal API key in PostHog instead.')]);
            }
            try {
                $values['base_url'] = PostHogClient::base($values['base_url']);
            } catch (ConnectorException) {
                throw ValidationException::withMessages(['values.base_url' => __('Use a public HTTPS server URL on port 443.')]);
            }
        }
        if (isset($values['base_url'])) {
            $url = parse_url($values['base_url']);
            if (! $url || ($url['scheme'] ?? null) !== 'https' || ! isset($url['host']) || isset($url['user']) || isset($url['pass']) || ($url['port'] ?? 443) !== 443 || isset($url['query']) || isset($url['fragment'])) {
                throw ValidationException::withMessages(['values.base_url' => __('Use a public HTTPS server URL on port 443.')]);
            }
        }

        return $values;
    }

    /**
     * The normalized Bluesky or Mastodon account, or a validation error that says which form to use.
     *
     * @throws ValidationException
     */
    public static function socialAccount(string $kind, string $input, string $field = 'config.account'): string
    {
        try {
            return $kind === 'bluesky' ? SocialAccount::bluesky($input) : SocialAccount::mastodon($input);
        } catch (ConnectorException $exception) {
            throw ValidationException::withMessages([$field => __($exception->getMessage())]);
        }
    }

    /** Providers that record a public follower count once a day. */
    public const FOLLOWER_PROVIDERS = ['bluesky', 'mastodon'];

    /** Upper bound on GitHub deployment environments per data source. */
    public const MAX_ENVIRONMENTS = 10;

    /** Providers that report their top search queries. */
    public const QUERY_PROVIDERS = ['gsc', 'bing'];

    /** Website analytics providers with audience breakdowns: channels, referrers, landing pages, campaigns, countries, devices and goals. */
    public const BREAKDOWN_PROVIDERS = ['ga4', 'plausible', 'umami', 'matomo', 'posthog'];

    private const WEBSITE_METRICS = ['visitors', 'visits', 'pageviews'];

    /** Shown as ratios; stored as their parts (see storedMetricKeys). */
    private const ENGAGEMENT_METRICS = ['bounce_rate', 'avg_visit_duration'];

    /**
     * Whether a data source of this provider and configuration reports audience breakdowns (a PostHog event source does not).
     *
     * @param  array<string, mixed>|null  $config
     */
    public static function hasBreakdowns(string $kind, ?array $config = null): bool
    {
        return in_array($kind, self::BREAKDOWN_PROVIDERS, true) && ! ($kind === 'posthog' && isset($config['event']));
    }

    /**
     * Parses comma-separated GitHub environment names: trimmed, without duplicates (ignoring case), at most ten.
     *
     * @return list<string>
     */
    public static function environments(string $input): array
    {
        $names = [];
        foreach (explode(',', $input) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                throw ValidationException::withMessages(['environments' => __('Use environment names separated by commas.')]);
            }
            $names[mb_strtolower($name)] ??= $name;
        }
        if (count($names) > self::MAX_ENVIRONMENTS) {
            throw ValidationException::withMessages(['environments' => __('Choose at most :count environments.', ['count' => self::MAX_ENVIRONMENTS])]);
        }

        return array_values($names);
    }

    /**
     * Identifies a data source within a project. Collection windows and event filters are settings, not identity.
     *
     * @param  array<string, mixed>  $config
     */
    public static function identityHash(string $kind, array $config, string $timezone): string
    {
        unset($config['days'], $config['limit'], $config['environments'], $config['convert_to_events']);
        ksort($config);

        return hash('sha256', json_encode([$kind, $config, $timezone], JSON_THROW_ON_ERROR));
    }

    /** @return list<string> Origins of the automatic events this provider imports. */
    public static function eventOrigins(string $kind): array
    {
        return match ($kind) {
            'github' => ['github_release', 'github_deployment'],
            'azure_devops' => ['azure_devops_deployment'],
            'feed' => ['feed_item'],
            'sitemap' => ['sitemap_url'],
            default => [],
        };
    }

    /**
     * Origins this provider turns into events when convert_to_events is on. Deployments are chosen separately.
     *
     * @return list<string>
     */
    public static function eventPreferenceOrigins(string $kind): array
    {
        return match ($kind) {
            'github' => ['github_release'],
            'feed' => ['feed_item'],
            'sitemap' => ['sitemap_url'],
            default => [],
        };
    }

    public static function convertsValuesToEvents(string $kind): bool
    {
        return self::eventPreferenceOrigins($kind) !== [];
    }

    /**
     * Missing means yes, so sources created before the preference keep importing events.
     *
     * @param  array<string, mixed>  $config
     */
    public static function importsEvents(array $config): bool
    {
        return ($config['convert_to_events'] ?? true) !== false;
    }

    /**
     * Drops the events controlled by convert_to_events, and does not reconcile them away.
     *
     * @param  array<string, mixed>  $config
     */
    public static function withoutConvertedEvents(string $kind, array $config, CollectionResult $result): CollectionResult
    {
        if (self::importsEvents($config)) {
            return $result;
        }
        $origins = self::eventPreferenceOrigins($kind);
        if ($origins === []) {
            return $result;
        }

        return new CollectionResult(
            daily: $result->daily,
            periods: $result->periods,
            events: array_values(array_filter($result->events, fn (array $event): bool => ! in_array($event['origin'], $origins, true))),
            authoritativeEvents: array_diff_key($result->authoritativeEvents, array_flip($origins)),
            replaceDailyDates: $result->replaceDailyDates,
            queries: $result->queries,
            queryMonths: $result->queryMonths,
            sitemapUrls: $result->sitemapUrls,
            channelDays: $result->channelDays,
            breakdowns: $result->breakdowns,
            breakdownDimensions: $result->breakdownDimensions,
            breakdownErrors: $result->breakdownErrors,
        );
    }

    /** Providers whose successful deployments to chosen environments become project events. */
    public static function hasDeploymentEnvironments(string $kind): bool
    {
        return self::deploymentOrigin($kind) !== null;
    }

    /** Origin of the deployment events a provider imports for its chosen environments. */
    public static function deploymentOrigin(string $kind): ?string
    {
        return match ($kind) {
            'github' => 'github_deployment',
            'azure_devops' => 'azure_devops_deployment',
            default => null,
        };
    }

    /** Providers whose dates follow the selected site or property, not a fixed offset. */
    public static function usesSiteTimezone(string $kind): bool
    {
        return in_array($kind, ['ga4', 'plausible', 'matomo', 'posthog'], true);
    }

    public static function requiresConnection(string $kind): bool
    {
        return ! in_array($kind, ['wporg', 'github_releases', 'feed', 'sitemap', 'bluesky', 'mastodon'], true) && ! ($kind === 'pagespeed' && app(PageSpeedCredentials::class)->managed());
    }

    /** A token can be saved, and a public repository also works without one. */
    public static function connectionOptional(string $kind): bool
    {
        return $kind === 'github_releases';
    }

    /**
     * Metrics a source writes: its metrics, with each ratio replaced by the metrics it is computed from.
     *
     * @param  array<string, mixed>|null  $config
     * @return list<string>
     */
    public static function storedMetricKeys(string $kind, ?array $config = null): array
    {
        $keys = [];
        foreach (self::metricKeys($kind, $config) as $key) {
            $ratio = MetricRegistry::get($key)->ratio;
            foreach ($ratio === null ? [$key] : [$ratio[0], $ratio[1]] as $stored) {
                $keys[$stored] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Metrics a source of this provider records. Without a configuration: every metric the provider can record.
     * A PostHog source records website and product usage, or, when it names an event, only that event's key events.
     *
     * @param  array<string, mixed>|null  $config
     * @return list<string>
     */
    public static function metricKeys(string $kind, ?array $config = null): array
    {
        if ($kind === 'posthog') {
            $usage = [...self::WEBSITE_METRICS, ...self::ENGAGEMENT_METRICS, 'active_users'];

            return $config === null ? [...$usage, 'key_events'] : (isset($config['event']) ? ['key_events'] : $usage);
        }

        return match ($kind) {
            'paddle' => ['paddle_revenue', 'paddle_mrr', 'paddle_active_subscribers'],
            'umami' => [...self::WEBSITE_METRICS, ...self::ENGAGEMENT_METRICS],
            'plausible','matomo','ga4' => [...self::WEBSITE_METRICS, ...self::ENGAGEMENT_METRICS, 'conversions'],'gsc' => ['impressions', 'clicks'],'bing' => ['impressions', 'clicks', 'indexed_pages'],'wporg' => ['downloads'],'feed' => ['published_items'],'sitemap' => ['new_urls', 'published_urls'],'github' => ['commits', 'prs_merged', 'loc_changed', 'open_issues'],'github_releases' => ['release_downloads'],'bluesky','mastodon' => ['followers'],'uptimerobot' => ['monitor_status', 'uptime_24h', 'response_time_24h'],'azure_devops' => ['commits', 'prs_merged', 'pipeline_runs_succeeded', 'pipeline_runs_failed', 'open_issues'],'pagespeed' => ['lab_score', 'lab_lcp', 'lab_cls', 'lab_tbt', 'crux_lcp', 'crux_inp', 'crux_cls', 'crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls'],default => []
        };
    }
}
