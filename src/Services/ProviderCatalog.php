<?php

namespace Metribay\Core\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProviderCatalog
{
    /** @return array<string, array{label: string, fields: list<string>, secrets: list<string>, config: list<string>, default_timezone: string, field_labels: array<string, string>}> */
    public static function all(): array
    {
        $data = [
            'umami' => ['Umami', ['base_url', 'username', 'password'], ['password'], ['website_id', 'days'], 'UTC'],
            'ga4' => ['Google Analytics 4', ['service_account', 'client_id', 'client_secret', 'refresh_token'], ['service_account', 'client_secret', 'refresh_token'], ['property_id', 'days'], 'UTC'],
            'gsc' => ['Google Search Console', ['service_account', 'client_id', 'client_secret', 'refresh_token'], ['service_account', 'client_secret', 'refresh_token'], ['site_url', 'days'], 'America/Los_Angeles'],
            'bing' => ['Bing Webmaster Tools', ['api_key'], ['api_key'], ['site_url', 'days'], 'UTC'],
            'wporg' => ['WordPress.org', [], [], ['slug', 'limit'], 'UTC'],
            'github' => ['GitHub', ['token', 'client_id', 'installation_id', 'private_key'], ['token', 'private_key'], ['repository', 'days'], 'UTC'],
            'uptimerobot' => ['UptimeRobot', ['api_key'], ['api_key'], ['monitor_id'], 'UTC'],
        ];
        $out = [];
        foreach ($data as $kind => [$label,$fields,$secrets,$config,$timezone]) {
            $out[$kind] = ['label' => $label, 'fields' => $fields, 'secrets' => $secrets, 'config' => $config, 'default_timezone' => $timezone, 'field_labels' => ['website_id' => 'Website UUID', 'base_url' => 'Server URL', 'service_account' => 'Service account JSON', 'site_url' => 'Site URL or Search Console property', 'private_key' => 'GitHub App private key (PEM)', 'token' => 'Personal access token', 'days' => 'History window (days)', 'limit' => 'History window (days)']];
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
            throw ValidationException::withMessages(['kind' => 'Choose a supported provider.']);
        }
        if (array_diff(array_keys($input), $provider['config'])) {
            throw ValidationException::withMessages(['config' => 'Unsupported source configuration field.']);
        }
        Validator::make(['timezone' => $timezone], ['timezone' => ['required', 'timezone']])->validate();
        if (in_array($kind, ['umami', 'bing', 'github', 'uptimerobot'], true) && ! in_array($timezone, ['UTC', 'Etc/UTC', 'Etc/GMT', 'GMT'], true)) {
            throw ValidationException::withMessages(['reporting_timezone' => 'This provider reports calendar dates in UTC.']);
        }
        if ($kind === 'gsc' && $timezone !== 'America/Los_Angeles') {
            throw ValidationException::withMessages(['reporting_timezone' => 'Search Console reports dates in America/Los_Angeles.']);
        }
        $rules = match ($kind) {
            'umami' => ['website_id' => ['required', 'uuid']],
            'ga4' => ['property_id' => ['required', 'regex:/^[0-9]+$/']],
            'gsc' => ['site_url' => ['required', 'string', 'max:500']],
            'bing' => ['site_url' => ['required', 'url:http,https', 'max:500']],
            'wporg' => ['slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'max:100']],
            'github' => ['repository' => ['required', 'regex:~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', 'max:240']],
            'uptimerobot' => ['monitor_id' => ['required', 'regex:/^[0-9]+$/']],
            default => throw ValidationException::withMessages(['kind' => 'Choose a supported provider.']),
        };
        $window = $kind === 'wporg' ? 'limit' : 'days';
        if (in_array($window, $provider['config'], true)) {
            $input[$window] = $input[$window] ?? 180;
            $rules[$window] = ['required', 'integer', 'min:1', 'max:366'];
        }
        $clean = Validator::make($input, $rules)->validate();
        if (isset($clean[$window])) {
            $clean[$window] = (int) $clean[$window];
        }
        foreach (['property_id', 'monitor_id'] as $numericId) {
            if (isset($clean[$numericId])) {
                $clean[$numericId] = (string) $clean[$numericId];
            }
        }
        if ($kind === 'gsc' && ! preg_match('~^(sc-domain:[a-z0-9.-]+|https?://[^\s]+)$~i', $clean['site_url'])) {
            throw ValidationException::withMessages(['config.site_url' => 'Use a Search Console URL or sc-domain: property.']);
        }
        if ($kind === 'github' && array_intersect(explode('/', $clean['repository']), ['.', '..'])) {
            throw ValidationException::withMessages(['config.repository' => 'Use owner/repository.']);
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
            throw ValidationException::withMessages(['kind' => 'This provider does not use a connection.']);
        }
        if (array_diff(array_keys($values), $provider['fields'])) {
            throw ValidationException::withMessages(['values' => 'Unsupported connection field.']);
        }
        $values = array_filter($values, fn ($value) => $value !== null && $value !== '');
        foreach ($values as $key => $value) {
            if (! is_string($value) && ! ($key === 'service_account' && is_array($value))) {
                throw ValidationException::withMessages(['values' => 'Credentials must be text or service account JSON.']);
            }if (is_string($value) && strlen($value) > 64000) {
                throw ValidationException::withMessages(['values' => 'Connection value is too long.']);
            }
        }
        $required = match ($kind) {
            'umami' => ['base_url', 'username', 'password'],'bing','uptimerobot' => ['api_key'],'github' => isset($values['token']) ? ['token'] : ['client_id', 'installation_id', 'private_key'],'gsc','ga4' => isset($values['service_account']) ? ['service_account'] : ['client_id', 'client_secret', 'refresh_token'],default => throw ValidationException::withMessages(['kind' => 'Choose a supported provider.'])
        };
        foreach ($required as $key) {
            if (empty($values[$key])) {
                throw ValidationException::withMessages(['values.'.$key => 'Complete the required connection fields.']);
            }
        }
        if (isset($values['service_account'])) {
            try {
                $account = is_array($values['service_account']) ? $values['service_account'] : json_decode($values['service_account'], true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($account) || empty($account['client_email']) || empty($account['private_key']) || ! openssl_pkey_get_private($account['private_key'])) {
                    throw new \RuntimeException;
                }$values['service_account'] = $account;
            } catch (\Throwable) {
                throw ValidationException::withMessages(['values.service_account' => 'Provide a valid service account JSON key.']);
            }
        }
        if (isset($values['base_url'])) {
            $url = parse_url($values['base_url']);
            if (! $url || ($url['scheme'] ?? null) !== 'https' || ! isset($url['host']) || isset($url['user']) || isset($url['pass']) || ($url['port'] ?? 443) !== 443 || isset($url['query']) || isset($url['fragment'])) {
                throw ValidationException::withMessages(['values.base_url' => 'Use a public HTTPS server URL on port 443.']);
            }
        }

        return $values;
    }

    /** @return list<string> */
    public static function metricKeys(string $kind): array
    {
        return match ($kind) {
            'umami','ga4' => ['visitors', 'visits', 'pageviews'],'gsc' => ['impressions', 'clicks'],'bing' => ['impressions', 'clicks', 'indexed_pages'],'wporg' => ['downloads'],'github' => ['commits', 'prs_merged', 'loc_changed', 'open_issues'],'uptimerobot' => ['monitor_status', 'uptime_24h', 'response_time_24h'],default => []
        };
    }
}
