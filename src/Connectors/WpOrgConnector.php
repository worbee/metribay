<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class WpOrgConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $slug = $this->text($source->config['slug'] ?? null, 'WordPress.org plugin slug', 200);
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new ConnectorException('Use a valid WordPress.org plugin slug.');
        }
        if (array_key_exists('limit', $source->config)) {
            $source = new SourceConfiguration($source->kind, array_replace($source->config, ['days' => $source->config['limit']]), $source->reportingTimezone, $source->from);
        }
        [$start, $today] = $this->window($source, $now, 2);
        // The statistics endpoint returns plausible counts for nonexistent slugs.
        $info = $this->json($this->http->request('GET', "https://api.wordpress.org/plugins/info/1.0/{$slug}.json"), 'WordPress.org plugin information');
        if (isset($info['error']) || ($info['slug'] ?? null) !== $slug) {
            throw new ConnectorException('The WordPress.org plugin was not found.');
        }
        $data = $this->json($this->http->request('GET', 'https://api.wordpress.org/stats/plugin/1.0/downloads.php', [
            'query' => ['slug' => $slug, 'limit' => max(2, (int) $start->diffInDays($today) + 1)],
        ]), 'WordPress.org downloads');
        $daily = [];
        ksort($data);
        foreach ($data as $rawDate => $value) {
            $date = $this->date($rawDate, 'WordPress.org');
            if ($date > $today->toDateString()) {
                throw new ConnectorException('WordPress.org returned a future daily date.');
            }
            $count = $this->count($value, 'WordPress.org');
            if ($date < $start->toDateString()) {
                continue;
            }
            $daily[] = $this->daily('downloads', $date, (float) $count, $date === $today->toDateString());
        }

        return new CollectionResult(daily: $daily);
    }
}
