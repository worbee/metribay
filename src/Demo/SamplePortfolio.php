<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

use Carbon\CarbonImmutable;

/**
 * The fictional sample portfolio: one project per audience of the website, on reserved .example domains.
 * Every number comes from the seed and "today", so the same seed on the same day gives the same data, and the story
 * (launches, releases, the search update, incidents) always sits at the same distance from today.
 * Events in this file and the effects they explain use the same "days ago" values; keep them together when editing.
 */
final class SamplePortfolio
{
    public const SEED = 2026;

    public const DAYS = 400;

    private readonly CarbonImmutable $now;

    public function __construct(private readonly int $seed = self::SEED, ?CarbonImmutable $now = null, private readonly int $days = self::DAYS)
    {
        $this->now = ($now ?? CarbonImmutable::now())->utc();
    }

    /** @return list<SampleProject> */
    public function projects(): array
    {
        return [$this->tallybird(), $this->quillform(), $this->driftwood(), $this->alderAndFinch(), $this->nordlicht(), $this->switchback(), $this->kestrel()];
    }

    /** Indie maker: a small invoicing SaaS with a Product Hunt launch 74 days ago. */
    private function tallybird(): SampleProject
    {
        $sources = $this->sources('tallybird');
        $newsletter = 208;
        $traffic = fn (int $ago, CarbonImmutable $date): float => 760 * Shape::growth($ago, [[400, 0.32], [300, 0.46], [220, 0.55], [130, 0.56], [75, 0.66], [0, 1.0]])
            * Shape::spike($ago, 74, 7.5, 2.2) * Calendar::weekday($date, 0.62) * Calendar::holiday($date, 0.5) * $this->monthly($ago, $newsletter, 1.25);
        $releases = $this->releases([372 => 'v1.6.0', 351 => 'v1.6.1', 330 => 'v1.7.0', 305 => 'v1.8.0', 288 => 'v1.8.1', 262 => 'v1.9.0', 240 => 'v1.10.0', 214 => 'v1.10.1',
            193 => 'v1.11.0', 171 => 'v1.12.0', 150 => 'v1.12.1', 128 => 'v1.13.0', 109 => 'v1.14.0', 90 => 'v2.0.0-beta.1', 76 => 'v2.0.0', 72 => 'v2.0.1', 61 => 'v2.1.0',
            44 => 'v2.2.0', 30 => 'v2.3.0', 16 => 'v2.3.1', 5 => 'v2.4.0'], ['v2.0.0' => 'Recurring invoices, client portal and a new editor.', 'v2.0.1' => 'Fixes PDF totals for invoices with discounts.',
                'v2.4.0' => 'Payment reminders can be scheduled per client.']);

        return new SampleProject('Tallybird', 'tallybird', 'tallybird.example', 'Indie makers and solo founders', [
            $sources->analytics('ga4', 'Google Analytics · tallybird.example', ['property_id' => '318204657', 'days' => 180], $traffic,
                ['pages' => 2.6, 'returning' => 1.18, 'loyalty' => 0.28, 'timezone' => 'Europe/Berlin']),
            $sources->search('gsc', 'sc-domain:tallybird.example', ['site_url' => 'sc-domain:tallybird.example', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 3500 * Shape::growth($ago, [[400, 0.4], [250, 0.6], [100, 0.8], [0, 1.0]]) * Shape::spike($ago, 74, 0.6, 4)
                    * Calendar::weekday($date, 0.7) * Calendar::holiday($date, 0.4),
                $this->queries([
                    ['tallybird', 1.1, ['brand' => true, 'weight' => 0.3]], ['tallybird login', 1.0, ['brand' => true, 'weight' => 0.3]], ['tallybird app', 1.2, ['brand' => true, 'weight' => 0.3]],
                    ['tallybird pricing', 1.3, ['brand' => true, 'weight' => 0.3]], ['invoice template for freelancers', 6.8], ['late payment email template', 4.2], ['tallybird invoice', 1.4, ['brand' => true, 'weight' => 0.3]],
                    ['how to write an invoice as a freelancer', 5.1], ['free invoice template', 14.2], ['freelance invoice software', 9.4], ['payment reminder email', 7.9],
                    ['recurring invoices for freelancers', 3.6], ['invoice app for designers', 4.4], ['tallybird review', 2.6, ['brand' => true, 'weight' => 0.4, 'since' => 70]],
                    ['simple invoicing app', 8.3], ['invoice number format', 11.7], ['what to include on an invoice', 9.9], ['freelance rate calculator', 6.2], ['net 30 meaning', 17.8],
                    ['payment terms on invoice', 12.5], ['invoice vs receipt', 15.1], ['quote template freelancer', 10.4], ['estimate vs invoice', 13.6], ['eu vat invoice requirements', 8.8],
                    ['reverse charge invoice', 12.9], ['invoice in multiple currencies', 7.4], ['invoice late fee wording', 6.6], ['time tracking and invoicing', 16.4],
                    ['how to get paid faster as a freelancer', 9.1], ['hourly invoice template', 8.0], ['invoicing for consultants', 11.2], ['online invoice payment link', 13.3],
                    ['send invoice by email', 19.2], ['invoice generator', 23.5], ['freelancer tax checklist', 21.4], ['invoice template word', 31.0],
                ]), ['coverage' => 0.58]),
            $sources->github('tallybird/app', fn (int $ago, CarbonImmutable $date): float => 3.2 * Calendar::weekday($date, 0.45) * Calendar::holiday($date, 0.8),
                ['repository' => 'tallybird/app', 'prs' => 0.12, 'loc' => 38, 'issues' => ['start' => 12, 'opened' => 0.42, 'closed' => 0.42], 'connected' => 160,
                    'releases' => $releases, 'deployments' => ['environment' => 'production', 'chance' => 0.55]]),
            $sources->uptime('https://tallybird.example', ['monitor' => '793104528', 'connected' => 210, 'response' => 280, 'incidents' => [23 => 34]]),
            $sources->pageSpeed(fn (int $ago): array => match (true) {
                $ago <= 12 => ['lcp' => 3100, 'tbt' => 210, 'cls' => 0.03],
                $ago <= 31 => ['lcp' => 3600, 'tbt' => 640, 'cls' => 0.04],
                default => ['lcp' => 3300, 'tbt' => 260, 'cls' => 0.04],
            }, ['url' => 'https://tallybird.example/', 'strategy' => 'mobile', 'connected' => 150, 'field' => 'url', 'field_lcp' => 0.55]),
        ], [
            $this->event(74, 'Product Hunt launch', 'green', 'Number three product of the day; most new sign-ups came the first weekend.'),
            $this->event(31, 'New marketing site', 'blue', 'Includes the live chat widget on every page.'),
            $this->event(12, 'Chat widget loads on click', 'blue'),
            $this->event($newsletter, 'Monthly newsletter', 'violet', recurrence: 'monthly'),
            $this->event(-18, 'Yearly plan promotion', 'green', 'Two months free on yearly plans for one week.'),
        ]);
    }

    /** WordPress plugin developer: downloads jump when installed sites update to a release. */
    private function quillform(): SampleProject
    {
        $sources = $this->sources('quillform');
        $versions = [388 => '3.4.0', 362 => '3.4.1', 331 => '3.5.0', 305 => '3.5.1', 284 => '3.6.0', 251 => '3.7.0', 222 => '3.7.1', 197 => '3.8.0', 170 => '3.9.0',
            141 => '4.0.0', 138 => '4.0.1', 118 => '4.0.2', 96 => '4.1.0', 71 => '4.1.1', 49 => '4.2.0', 27 => '4.2.1', 9 => '4.3.0'];
        $updates = [];
        foreach ($versions as $ago => $version) {
            $updates[$ago] = match (true) {
                $version === '4.0.0' => 10500.0, $version === '4.0.1' => 8000.0, str_ends_with($version, '.0') => 6500.0, default => 4000.0,
            };
        }
        $releases = $this->releases(array_map(fn (string $version): string => 'v'.$version, $versions),
            ['v4.0.0' => 'Multi-step forms, a new block editor experience and conditional logic for every field.', 'v4.0.1' => 'Fixes a conflict with page builders that load jQuery late.',
                'v4.3.0' => 'Entries can be sent to a webhook; spam filtering improved.']);
        $talk = 58;

        return new SampleProject('Quillform', 'quillform', 'quillform.example', 'WordPress plugin and theme developers', [
            $sources->downloads('quillform', fn (int $ago, CarbonImmutable $date): float => 640 * Shape::growth($ago, [[400, 0.78], [200, 0.9], [0, 1.05]])
                * Calendar::weekday($date, 0.72) * Calendar::holiday($date, 0.4), $updates, [131]),
            $sources->github('quillform/quillform', fn (int $ago, CarbonImmutable $date): float => 2.4 * Calendar::weekday($date, 0.3) * Calendar::holiday($date, 0.85),
                ['repository' => 'quillform/quillform', 'prs' => 0.55, 'loc' => 52, 'issues' => ['start' => 41, 'opened' => 1.3, 'closed' => 1.25], 'connected' => 190, 'releases' => $releases]),
            $sources->releaseDownloads('quillform/quillform', 180, 86000),
            $sources->search('gsc', 'sc-domain:quillform.example', ['site_url' => 'sc-domain:quillform.example', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 2500 * Shape::growth($ago, [[400, 0.75], [141, 0.85], [100, 1.0], [0, 1.08]]) * Shape::spike($ago, $talk, 0.35, 3)
                    * Calendar::weekday($date, 0.6) * Calendar::holiday($date, 0.45),
                $this->queries([
                    ['quillform', 1.0, ['brand' => true, 'weight' => 0.4]], ['conditional logic form wordpress', 3.1], ['wordpress multi step form', 4.3], ['quillform pro', 1.1, ['brand' => true, 'weight' => 0.4]],
                    ['best form plugin for wordpress', 8.7], ['wordpress contact form plugin', 11.5], ['gutenberg form block', 5.6], ['quillform docs', 1.2, ['brand' => true, 'weight' => 0.4]],
                    ['wordpress form to google sheets', 6.9], ['file upload form wordpress', 7.7], ['honeypot field wordpress', 4.9], ['wordpress form not sending email', 5.4],
                    ['gdpr contact form wordpress', 9.3], ['calculation form wordpress', 6.1], ['wordpress form webhook', 3.8, ['since' => 9]], ['wordpress form spam protection', 10.8],
                    ['save form entries wordpress', 7.1], ['wordpress quote request form', 8.2], ['quillform conditional logic', 1.4, ['brand' => true, 'weight' => 0.4, 'since' => 141]],
                    ['accessible forms wordpress', 4.6], ['repeater field form wordpress', 6.4], ['email notification wordpress form', 9.7], ['wordpress form autosave', 3.3],
                    ['form analytics wordpress', 11.0], ['smtp for wordpress forms', 12.2], ['wordpress survey plugin', 15.6], ['wordpress booking form', 18.9], ['wordpress payment form', 21.3],
                ]), ['coverage' => 0.6]),
            $sources->analytics('umami', 'quillform.example', ['website_id' => '5b9e3c1a-7d42-4f0e-9a61-2c8d1e4f7a30', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 480 * Shape::growth($ago, [[400, 0.8], [141, 0.9], [100, 1.0], [0, 1.1]]) * Shape::spike($ago, $talk, 1.4, 1.5)
                    * Shape::spike($ago, 141, 0.8, 3) * Calendar::weekday($date, 0.55) * Calendar::holiday($date, 0.5),
                ['pages' => 2.3, 'returning' => 1.12, 'loyalty' => 0.18]),
            $sources->uptime('https://quillform.example', ['monitor' => '781662043', 'connected' => 260, 'response' => 340, 'incidents' => [137 => 12]]),
        ], [
            $this->event(141, '4.0 launch post', 'green', 'Announcement on the blog and the plugin directory.'),
            $this->event($talk, 'WordCamp talk: forms people finish', 'violet', 'Recording and slides linked from the docs.'),
        ]);
    }

    /** Open-source maintainer: a busy TypeScript library with contributors, monthly releases and a documentation site. */
    private function driftwood(): SampleProject
    {
        $sources = $this->sources('driftwood');
        $releases = $this->releases([395 => 'v3.2.0', 366 => 'v3.3.0', 340 => 'v3.3.1', 309 => 'v3.4.0', 279 => 'v3.5.0', 251 => 'v3.5.1', 220 => 'v4.0.0-rc.1', 206 => 'v4.0.0',
            199 => 'v4.0.1', 173 => 'v4.1.0', 142 => 'v4.2.0', 113 => 'v4.2.1', 85 => 'v4.3.0', 57 => 'v4.4.0', 33 => 'v4.4.1', 12 => 'v4.5.0'],
            ['v4.0.0' => 'Typed transitions, parallel states and a smaller core (3.1 kB).', 'v4.5.0' => 'Persisted machines can be restored from JSON.']);
        $shape = fn (int $ago, CarbonImmutable $date): float => Shape::growth($ago, [[400, 0.55], [207, 0.7], [190, 0.85], [0, 1.08]]) * Shape::spike($ago, 206, 0.9, 4)
            * Shape::spike($ago, 120, 0.5, 2) * Calendar::weekday($date, 0.4) * Calendar::holiday($date, 0.6);
        $queries = $this->queries([
            ['driftwood js', 1.0, ['brand' => true, 'weight' => 0.5]], ['typescript state machine', 2.8], ['driftwood state machine', 1.0, ['brand' => true, 'weight' => 0.5]],
            ['state machine library javascript', 4.1], ['typed state machine typescript', 2.2], ['finite state machine javascript', 6.3], ['driftwood docs', 1.1, ['brand' => true, 'weight' => 0.5]],
            ['state machine react', 5.2], ['tiny state machine library', 3.2], ['fsm typescript example', 3.9], ['traffic light state machine example', 4.4], ['statecharts vs state machines', 7.5],
            ['state machine vs reducer', 5.9], ['undo redo with state machine', 6.8], ['driftwood react', 1.3, ['brand' => true, 'weight' => 0.5]], ['parallel states javascript', 10.9, ['since' => 206]],
            ['async state transitions', 7.7], ['state machine guard condition', 8.4], ['hierarchical state machine js', 8.9], ['state machine vue', 9.1], ['state machine svelte', 7.3],
            ['react form state machine', 9.6], ['state machine testing', 10.4], ['state machine persistence', 11.6, ['since' => 12]], ['state machine visualizer', 12.3],
            ['event driven state machine node', 13.8], ['state machine diagram to code', 15.5], ['javascript workflow engine', 17.2],
        ]);

        return new SampleProject('Driftwood', 'driftwood', 'driftwood.example', 'Open-source maintainers', [
            $sources->github('driftwood-js/driftwood', fn (int $ago, CarbonImmutable $date): float => 6.5 * Calendar::weekday($date, 0.35) * Calendar::holiday($date, 0.75),
                ['repository' => 'driftwood-js/driftwood', 'prs' => 0.85, 'loc' => 64, 'issues' => ['start' => 118, 'opened' => 3.5, 'closed' => 3.0], 'connected' => 300, 'releases' => $releases]),
            $sources->analytics('umami', 'driftwood.example', ['website_id' => '0e6f2a9b-3c1d-4b8e-a5f7-91d2c4e6b803', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 1350 * $shape($ago, $date) * Shape::spike($ago, 39, 2.4, 1.6), ['pages' => 2.9, 'returning' => 1.25, 'loyalty' => 0.3]),
            $sources->search('gsc', 'sc-domain:driftwood.example', ['site_url' => 'sc-domain:driftwood.example', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 5000 * $shape($ago, $date), $queries, ['coverage' => 0.62]),
            $sources->search('bing', 'https://driftwood.example/', ['site_url' => 'https://driftwood.example/', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 520 * $shape($ago, $date), $queries,
                ['coverage' => 0.55, 'indexed' => fn (int $ago): float => 140 + 4 * intdiv(18 * ($this->days - $ago), $this->days)]),
        ], [
            $this->event(206, 'Driftwood 4.0', 'green', 'Launch post, a conference lightning talk and a new documentation site.'),
            $this->event(120, 'Conference talk: state machines in the wild', 'violet'),
            $this->event(39, 'Featured in a frontend newsletter', 'green'),
        ]);
    }

    /** Agency client: a neighbourhood bakery's website, with campaigns, a slow hero video and a hosting move. */
    private function alderAndFinch(): SampleProject
    {
        $sources = $this->sources('alder-and-finch');
        $week = [1 => 0.85, 5 => 1.15, 6 => 1.3, 7 => 1.22];

        return new SampleProject('Alder & Finch', 'alder-and-finch', 'alderandfinch.example', 'Agencies and freelancers', [
            $sources->analytics('ga4', 'Google Analytics · alderandfinch.example', ['property_id' => '412739018', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 310 * Shape::growth($ago, [[400, 0.8], [0, 1.12]]) * Calendar::weekday($date, 1.0, $week)
                    * Calendar::season($date, 0.12, 350) * Calendar::holiday($date, 0.35) * Shape::spike($ago, 205, 0.6, 5) * Shape::spike($ago, 33, 0.8, 3),
                ['pages' => 2.2, 'returning' => 1.08, 'loyalty' => 0.12, 'timezone' => 'America/Los_Angeles', 'bots' => [19 => 820]]),
            $sources->search('gsc', 'sc-domain:alderandfinch.example', ['site_url' => 'sc-domain:alderandfinch.example', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 1600 * Shape::growth($ago, [[400, 0.85], [0, 1.1]]) * Calendar::weekday($date, 1.0, [6 => 1.2, 7 => 1.15])
                    * Calendar::season($date, 0.15, 350) * Calendar::holiday($date, 0.3),
                $this->queries([
                    ['alder and finch', 1.0, ['brand' => true, 'weight' => 0.35]], ['bakery near me', 6.4], ['alder & finch bakery', 1.0, ['brand' => true, 'weight' => 0.35]],
                    ['sourdough bread near me', 3.9], ['alder and finch menu', 1.1, ['brand' => true, 'weight' => 0.35]], ['croissants near me', 4.7], ['custom birthday cake', 8.6],
                    ['alder and finch hours', 1.0, ['brand' => true, 'weight' => 0.35]], ['best cinnamon rolls', 5.5], ['bakery catering', 6.9], ['sourdough starter for sale', 4.1],
                    ['christmas cookies order', 5.0, ['peak' => 352, 'season' => 0.95]], ['pumpkin bread', 7.6, ['peak' => 300, 'season' => 0.85]], ['wedding cake tasting', 7.2],
                    ['baking class', 8.0, ['peak' => 290, 'season' => 0.5]], ['gluten free bakery', 9.8], ['hot cross buns', 6.3, ['peak' => 95, 'season' => 0.95]],
                    ['bread subscription', 3.4], ['alder and finch cakes', 1.2, ['brand' => true, 'weight' => 0.35]], ['breakfast pastries', 9.4], ['cafe with wifi', 11.4], ['vegan cake', 12.7],
                    ['king cake', 14.0, ['peak' => 40, 'season' => 0.95]],
                ]), ['coverage' => 0.66, 'rare' => 0.24]),
            $sources->uptime('https://alderandfinch.example', ['monitor' => '779250871', 'connected' => 300, 'response' => 690,
                'steps' => fn (int $ago): float => Shape::step($ago, 45, 0.45), 'incidents' => [201 => 7, 52 => 68]]),
            $sources->pageSpeed(fn (int $ago): array => match (true) {
                $ago <= 15 => ['lcp' => 3000, 'tbt' => 260, 'cls' => 0.03],
                $ago <= 40 => ['lcp' => 5600, 'tbt' => 360, 'cls' => 0.18],
                default => ['lcp' => 3500, 'tbt' => 300, 'cls' => 0.07],
            }, ['url' => 'https://alderandfinch.example/', 'strategy' => 'mobile', 'connected' => 230, 'field' => 'url', 'field_lcp' => 0.6, 'inp' => 190]),
        ], [
            $this->event(300, 'Monthly client report', 'violet', recurrence: 'monthly'),
            $this->event(205, 'Spring menu campaign', 'green', 'Newsletter, posters in the shop and a small social budget.'),
            $this->event(45, 'Moved to new hosting', 'amber', 'From shared hosting to a managed WordPress host.'),
            $this->event(40, 'New homepage with hero video', 'blue'),
            $this->event(33, 'Autumn baking classes campaign', 'green'),
            $this->event(15, 'Homepage images optimised', 'blue', 'Poster image instead of autoplay video on phones; images resized.'),
            $this->event(-11, 'Holiday pre-orders open', 'green'),
        ]);
    }

    /** Fractional CTO client: a B2B logistics platform on Azure DevOps with an API monitor. */
    private function nordlicht(): SampleProject
    {
        $sources = $this->sources('nordlicht');

        return new SampleProject('Nordlicht Logistics', 'nordlicht', 'nordlicht.example', 'Fractional leaders', [
            $sources->azure('Platform/platform-api', fn (int $ago, CarbonImmutable $date): float => 14 * Calendar::weekday($date, 0.03) * Calendar::holiday($date, 0.9),
                ['repository' => 'Platform/platform-api', 'organization' => 'nordlicht', 'prs' => 0.32, 'loc' => 45, 'issues' => ['start' => 34, 'opened' => 1.6, 'closed' => 1.5],
                    'connected' => 150, 'failure' => 0.07, 'flaky' => [19, 17], 'deployments' => ['environment' => 'Production', 'weekdays' => [2, 4]]]),
            $sources->analytics('ga4', 'Google Analytics · nordlicht.example', ['property_id' => '389120554', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 410 * Shape::growth($ago, [[400, 0.7], [180, 0.85], [0, 1.0]]) * Calendar::weekday($date, 0.28)
                    * Calendar::holiday($date, 0.75) * Calendar::season($date, 0.08, 60),
                ['pages' => 2.4, 'returning' => 1.3, 'loyalty' => 0.32, 'timezone' => 'Europe/Berlin']),
            $sources->uptime('https://api.nordlicht.example/health', ['monitor' => '784417390', 'connected' => 330, 'response' => 185, 'incidents' => [87 => 4, 6 => 22]]),
            $sources->pageSpeed(fn (int $ago): array => $ago <= 64 ? ['lcp' => 1150, 'tbt' => 130, 'cls' => 0.02] : ['lcp' => 1100, 'tbt' => 70, 'cls' => 0.02],
                ['url' => 'https://nordlicht.example/', 'strategy' => 'desktop', 'connected' => 120, 'field' => 'origin', 'field_lcp' => 1.3, 'inp' => 90]),
        ], [
            $this->event(180, 'Monthly steering call', 'violet', recurrence: 'monthly'),
            $this->event(64, 'Cookie consent banner', 'blue'),
            $this->event(29, 'Q4 roadmap kickoff', 'blue'),
            $this->event(17, 'Flaky integration tests quarantined', 'amber', 'Two order-import tests depended on wall-clock time.'),
            $this->event(-9, 'Security review', 'blue'),
        ]);
    }

    /** Content and SEO publisher: a hiking blog with strong seasons and a search update 46 days ago. */
    private function switchback(): SampleProject
    {
        $sources = $this->sources('switchback');
        $update = 46;
        $shape = fn (int $ago, CarbonImmutable $date): float => Calendar::season($date, 0.26, 175) * Shape::growth($ago, [[400, 0.85], [200, 0.98], [0, 1.06]])
            * Calendar::weekday($date, 1.16) * Calendar::holiday($date, 0.2) * Shape::spike($ago, 20, 0.25, 4);
        $queries = $this->queries([
            ['how many miles can you hike in a day', 1.8], ['day hike packing list', 2.4, ['peak' => 160, 'season' => 0.4]], ['are trekking poles worth it', 2.2],
            ['how to choose hiking boots', 3.2, ['peak' => 120, 'season' => 0.3]], ['hiking boots vs trail runners', 2.6], ['how to read a topographic map', 2.9],
            ['what is a switchback in hiking', 1.4], ['winter hiking gear list', 3.9, ['peak' => 15, 'season' => 0.85]], ['what to wear hiking in autumn', 3.1, ['peak' => 285, 'season' => 0.8]],
            ['blister prevention hiking', 3.5, ['peak' => 180, 'season' => 0.3]], ['hiking in the rain tips', 3.7, ['peak' => 300, 'season' => 0.3]], ['beginner backpacking checklist', 3.0, ['peak' => 140, 'season' => 0.5]],
            ['how much water to bring hiking', 2.7, ['peak' => 200, 'season' => 0.45]], ['microspikes vs crampons', 3.3, ['peak' => 20, 'season' => 0.9]], ['layering for hiking', 4.1, ['peak' => 300, 'season' => 0.4]],
            ['how to layer for winter hiking', 3.4, ['peak' => 10, 'season' => 0.85]], ['how to use a compass', 4.0], ['how to pack a backpack for hiking', 3.6],
            ['how to purify water when hiking', 4.4, ['peak' => 190, 'season' => 0.4]], ['hiking calories burned', 4.3], ['leave no trace principles', 4.8],
            ['snowshoeing for beginners', 4.6, ['peak' => 25, 'season' => 0.95]], ['bear safety hiking', 4.9, ['peak' => 180, 'season' => 0.5]], ['hiking etiquette', 5.1],
            ['hiking snacks', 5.3, ['peak' => 180, 'season' => 0.4]], ['how to train for a long hike', 5.7, ['peak' => 120, 'season' => 0.35]], ['hiking with a dog', 5.9, ['peak' => 170, 'season' => 0.35]],
            ['how to prevent altitude sickness', 6.0, ['peak' => 200, 'season' => 0.4]], ['first aid kit for hiking', 6.2], ['best hikes for beginners', 6.8, ['peak' => 150, 'season' => 0.45]],
            ['hiking with kids', 6.6, ['peak' => 180, 'season' => 0.4]], ['sunrise hike tips', 7.4, ['peak' => 190, 'season' => 0.5]], ['merino wool hiking socks', 7.8, ['peak' => 320, 'season' => 0.3]],
            ['hiking after rain safety', 8.2], ['best headlamp for hiking', 8.9, ['peak' => 330, 'season' => 0.4]], ['fall foliage hikes', 9.1, ['peak' => 285, 'season' => 0.9]],
            ['lightweight backpacking tent', 9.6, ['peak' => 140, 'season' => 0.5]], ['trail running for beginners', 10.2, ['peak' => 130, 'season' => 0.4]],
            ['hut to hut hiking', 12.6, ['peak' => 170, 'season' => 0.5]], ['switchback journal', 1.0, ['brand' => true, 'weight' => 1.5]],
        ], 0.9);

        return new SampleProject('Switchback Journal', 'switchback-journal', 'switchbackjournal.example', 'Content and SEO publishers', [
            $sources->analytics('ga4', 'Google Analytics · switchbackjournal.example', ['property_id' => '297745810', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 5600 * $shape($ago, $date) * Shape::step($ago, $update, 0.76, 12),
                ['pages' => 1.9, 'returning' => 1.04, 'loyalty' => 0.06, 'timezone' => 'America/Denver']),
            $sources->search('gsc', 'sc-domain:switchbackjournal.example', ['site_url' => 'sc-domain:switchbackjournal.example', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 45000 * $shape($ago, $date) * Shape::step($ago, $update, 0.9, 12), $queries,
                ['coverage' => 0.55, 'rare' => 0.4, 'shift' => fn (int $ago, bool $brand): float => $brand ? 0.0 : 0.5 * (Shape::step($ago, $update, 2, 12) - 1)]),
            $sources->search('bing', 'https://switchbackjournal.example/', ['site_url' => 'https://switchbackjournal.example/', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 5000 * $shape($ago, $date), $queries,
                ['coverage' => 0.5, 'rare' => 0.35, 'indexed' => fn (int $ago): float => 380 + 3 * intdiv(45 * ($this->days - $ago), $this->days)]),
        ], [
            $this->event(250, 'Site redesign', 'blue', 'Faster article template and a new navigation.'),
            $this->event(160, 'Summer trail guides series', 'green'),
            $this->event($update, 'Google core update', 'amber', 'Rolled out over about two weeks; most guides slipped a little in the rankings.'),
            $this->event(20, 'Winter gear guide series', 'green'),
        ]);
    }

    /** Self-hoster: a photographer's site with self-hosted Umami and a tracker outage during a server upgrade. */
    private function kestrel(): SampleProject
    {
        $sources = $this->sources('kestrel');

        return new SampleProject('Kestrel Photo', 'kestrel-photo', 'kestrelphoto.example', 'Self-hosters and privacy-first teams', [
            $sources->analytics('umami', 'kestrelphoto.example', ['website_id' => '8c2d7f41-6a3e-4d95-b0c8-5e1f9a2b7d64', 'days' => 180],
                fn (int $ago, CarbonImmutable $date): float => 82 * Shape::growth($ago, [[400, 0.7], [0, 1.1]]) * Calendar::weekday($date, 1.2) * Calendar::holiday($date, 0.2)
                    * Calendar::season($date, 0.15, 200) * Shape::spike($ago, 140, 3.0, 1.2),
                ['pages' => 2.8, 'returning' => 1.1, 'loyalty' => 0.1, 'gaps' => [64, 63], 'sigma' => 0.14]),
            $sources->uptime('https://kestrelphoto.example', ['monitor' => '790038215', 'connected' => 380, 'response' => 420,
                'steps' => fn (int $ago): float => Shape::step($ago, 35, 0.45), 'incidents' => [158 => 14, 64 => 190, 63 => 75]]),
            $sources->pageSpeed(fn (int $ago): array => ['lcp' => $ago <= 35 ? 3500 : 4100, 'tbt' => 150, 'cls' => 0.01],
                ['url' => 'https://kestrelphoto.example/', 'strategy' => 'mobile', 'connected' => 100, 'field' => 'none']),
            $sources->github('kestrel/site', fn (int $ago, CarbonImmutable $date): float => 0.6 * Calendar::weekday($date, 1.8) * Calendar::holiday($date, -0.6),
                ['repository' => 'kestrel/site', 'prs' => 0.05, 'loc' => 30, 'issues' => ['start' => 3, 'opened' => 0.05, 'closed' => 0.05], 'connected' => 380, 'releases' => []]),
        ], [
            $this->event(140, 'Photo featured on a forum', 'green'),
            $this->event(64, 'Umami server upgrade', 'amber', 'The tracker was offline for two days; those days have no data.'),
            $this->event(35, 'Moved to a new VPS', 'amber'),
        ]);
    }

    private function sources(string $project): SampleSources
    {
        return new SampleSources(new Noise($this->seed, $project), $this->now, $this->days);
    }

    /** A bump on the day a monthly newsletter goes out (and a smaller one the day after), from $since days ago. */
    private function monthly(int $ago, int $since, float $bump): float
    {
        if ($ago > $since) {
            return 1.0;
        }
        $first = $this->now->startOfDay()->subDays($since);
        for ($n = 0; ($date = $first->addMonthsNoOverflow($n))->lte($this->now); $n++) {
            $distance = (int) $date->diffInDays($this->now->startOfDay()->subDays($ago), false);
            if ($distance === 0 || $distance === 1) {
                return $distance === 0 ? $bump : 1 + ($bump - 1) / 3;
            }
        }

        return 1.0;
    }

    /**
     * @param  array<int, string>  $tags  Tags by days ago.
     * @param  array<string, string>  $notes
     * @return list<array{ago: int, tag: string, notes?: string, prerelease?: bool}>
     */
    private function releases(array $tags, array $notes = []): array
    {
        $releases = [];
        foreach ($tags as $ago => $tag) {
            $releases[] = ['ago' => $ago, 'tag' => $tag, 'prerelease' => str_contains($tag, '-')] + (isset($notes[$tag]) ? ['notes' => $notes[$tag]] : []);
        }

        return $releases;
    }

    /**
     * Top queries with Zipf-like weights by their order: the first is searched most.
     *
     * @param  list<array{0: string, 1: float, 2?: array{brand?: bool, weight?: float, peak?: int, season?: float, since?: int}}>  $list  Query, average position, options.
     * @return list<array{query: string, weight: float, position: float, brand?: bool, peak?: int, season?: float, since?: int}>
     */
    private function queries(array $list, float $exponent = 1.0): array
    {
        $queries = [];
        foreach ($list as $rank => $item) {
            $options = $item[2] ?? [];
            $weight = ($options['weight'] ?? 1.0) / ($rank + 1) ** $exponent;
            unset($options['weight']);
            $queries[] = ['query' => $item[0], 'weight' => $weight, 'position' => $item[1]] + $options;
        }

        return $queries;
    }

    /**
     * A custom event $ago days back (negative for upcoming ones).
     *
     * @return array{date: string, title: string, color: string, body?: string, recurrence?: string}
     */
    private function event(int $ago, string $title, string $color, ?string $body = null, ?string $recurrence = null): array
    {
        return ['date' => $this->now->startOfDay()->subDays($ago)->toDateString(), 'title' => $title, 'color' => $color]
            + ($body !== null ? ['body' => $body] : []) + ($recurrence !== null ? ['recurrence' => $recurrence] : []);
    }
}
