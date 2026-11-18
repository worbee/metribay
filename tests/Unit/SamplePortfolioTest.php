<?php

namespace Metribay\Core\Tests\Unit;

use Carbon\CarbonImmutable;
use Metribay\Core\Demo\SamplePortfolio;
use Metribay\Core\Demo\SampleSources;
use Metribay\Core\Services\ProviderCatalog;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SamplePortfolioTest extends TestCase
{
    private const DAYS = 100;

    /** Builds fourteen full portfolios, so it runs in its own process to stay clear of the suite's memory limit. */
    #[RunInSeparateProcess]
    public function test_no_sample_source_looks_delayed_on_any_day(): void
    {
        // Health marks a source delayed when its newest data is older than three days (DashboardData).
        foreach (range(0, 13) as $offset) {
            $now = CarbonImmutable::parse('2026-11-03 01:20:00', 'UTC')->addDays($offset)->addHours($offset % 2 === 0 ? 0 : 21);
            foreach ((new SamplePortfolio(SamplePortfolio::SEED, $now, SamplePortfolio::DAYS))->projects() as $project) {
                foreach ($project->sources as $source) {
                    $dates = [];
                    foreach ($source->collections as $collection) {
                        foreach ($collection['result']->daily as $row) {
                            $dates[] = $row['date'] instanceof CarbonImmutable ? $row['date']->toDateString() : (string) $row['date'];
                        }
                    }
                    $limit = $now->setTimezone($source->timezone)->startOfDay()->subDays(3)->toDateString();
                    $this->assertNotSame([], $dates, $project->slug.' · '.$source->label);
                    $this->assertGreaterThanOrEqual($limit, max($dates), $project->slug.' · '.$source->label.' on '.$now->toDateTimeString());
                }
            }
        }
    }

    /** Builds two full portfolios. In the suite's process it ran out of memory once the 1.4.0 PostHog samples grew them. */
    #[RunInSeparateProcess]
    public function test_the_same_seed_and_day_give_the_same_portfolio(): void
    {
        $now = CarbonImmutable::parse('2026-11-03 15:20:00', 'UTC');
        $first = $this->fingerprint(7, $now);

        $this->assertSame($first, $this->fingerprint(7, $now));
        $this->assertNotSame($first, $this->fingerprint(8, $now));
    }

    private function fingerprint(int $seed, CarbonImmutable $now): string
    {
        // Hash project by project: one serialized string of the whole portfolio needs a large share of the test memory limit.
        $hash = hash_init('sha256');
        foreach ((new SamplePortfolio($seed, $now, self::DAYS))->projects() as $project) {
            hash_update($hash, serialize($project));
        }

        return hash_final($hash);
    }

    public function test_the_portfolio_covers_every_provider_metric_and_event_origin(): void
    {
        $projects = (new SamplePortfolio(SamplePortfolio::SEED, CarbonImmutable::parse('2026-11-03 15:20:00', 'UTC'), self::DAYS))->projects();
        $metrics = [];
        $origins = [];
        foreach ($projects as $project) {
            $this->assertStringEndsWith('.example', $project->domain);
            foreach ($project->sources as $source) {
                foreach ($source->collections as $collection) {
                    foreach ($collection['result']->daily as $row) {
                        $metrics[$source->kind][$row['metric']] = true;
                    }
                    foreach ($collection['result']->events as $event) {
                        $origins[$event['origin']] = true;
                    }
                }
            }
        }

        $this->assertCount(7, $projects);
        $this->assertCount(7, array_unique(array_map(fn ($project) => $project->audience, $projects)));
        foreach (array_keys(ProviderCatalog::all()) as $kind) {
            $this->assertEqualsCanonicalizing(ProviderCatalog::metricKeys($kind), array_keys($metrics[$kind] ?? []), $kind);
        }
        $this->assertEqualsCanonicalizing(['github_release', 'github_deployment', 'azure_devops_deployment', 'feed_item', 'sitemap_url'], array_keys($origins));
    }

    public function test_lighthouse_scores_follow_the_published_curves(): void
    {
        // At each metric's 10th-percentile control point the score is 0.9; at the median it is 0.5.
        $this->assertEqualsWithDelta(0.9, SampleSources::lighthouse('mobile', 1800, 3387, 2500, 200, 0.1), 0.001);
        $this->assertEqualsWithDelta(0.5, SampleSources::lighthouse('desktop', 1600, 2300, 2400, 350, 0.25), 0.001);
    }
}
