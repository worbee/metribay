<?php

namespace Metribay\Core\Tests\Unit;

use Metribay\Core\Metrics\ProjectViews;
use PHPUnit\Framework\TestCase;

class ProjectViewsTest extends TestCase
{
    public function test_presets_win_over_a_custom_mix_and_unknown_values_stay_on_all_metrics(): void
    {
        $this->assertSame(['kind' => 'overview', 'preset' => null, 'groups' => []], ProjectViews::resolve('overview', 'search,revenue'));
        $this->assertSame(['kind' => 'preset', 'preset' => 'development', 'groups' => ['development']], ProjectViews::resolve('development', 'search'));
        $this->assertSame(['kind' => 'custom', 'preset' => null, 'groups' => ['audience', 'search']], ProjectViews::resolve('metrics', 'search, nope, search, audience'));
        $this->assertSame(['kind' => 'custom', 'preset' => null, 'groups' => ['revenue']], ProjectViews::resolve('nope', ['revenue', 'made-up']));
        $this->assertSame(['kind' => 'metrics', 'preset' => null, 'groups' => []], ProjectViews::resolve('nope', 'made-up'));
        $this->assertSame(['kind' => 'metrics', 'preset' => null, 'groups' => []], ProjectViews::resolve('', null));
    }

    public function test_links_keep_one_view_and_the_reporting_period_can_be_added_around_it(): void
    {
        $this->assertSame(['view' => 'overview'], ProjectViews::linkQuery(ProjectViews::resolve('overview', '')));
        $this->assertSame(['view' => 'search'], ProjectViews::linkQuery(ProjectViews::resolve('search', '')));
        $this->assertSame(['groups' => 'audience,search'], ProjectViews::linkQuery(ProjectViews::resolve('', 'search,audience')));
        $this->assertSame([], ProjectViews::linkQuery(ProjectViews::resolve('', '')));
    }

    public function test_every_locale_has_the_view_strings(): void
    {
        $keys = [
            'A view with this name is already saved.',
            'Chart groups',
            'Choose at least one chart group.',
            'Custom view',
            'Development and Issues',
            'Enter a view name.',
            'No saved views yet.',
            'Remove view',
            'Rename view',
            'Save view',
            'Saved views',
            'This project has no charts in this view.',
            'View name',
            'View removed.',
            'View renamed.',
            'View saved in this browser.',
            'You can save up to :count views for this project.',
        ];
        foreach (['bg', 'cs', 'de', 'es', 'fr', 'it', 'nl', 'pl', 'sk', 'sl', 'sr'] as $locale) {
            $file = dirname(__DIR__, 2).'/lang/'.$locale.'.json';
            $messages = json_decode((string) file_get_contents($file), true);
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $messages, $locale.' is missing '.$key);
                $this->assertNotSame('', $messages[$key], $locale.' has an empty '.$key);
            }
        }
    }
}
