@php
    $providerNames = ['ga4'=>'Google Analytics','gsc'=>'Google Search Console','bing'=>'Bing Webmaster','umami'=>'Umami Analytics','github'=>'GitHub','azure_devops'=>'Azure DevOps','wporg'=>'WordPress.org','uptimerobot'=>'UptimeRobot','pagespeed'=>'PageSpeed Insights'];
    $groupId = 'group-'.$group['key'];
    $multiple = count($group['sources']) > 1;
    $charts = collect($group['cards'])->whereIn('size', ['l', 's']);
    $hasPrevious = $charts->contains(fn ($card) => collect($card['metric']['previous_series'] ?? [])->contains(fn ($point) => ($point['value'] ?? null) !== null));
@endphp
<section @class(['project-group', 'is-half' => $group['half']]) id="{{ $groupId }}" aria-labelledby="{{ $groupId }}-heading" data-chart-group wire:key="{{ $groupId }}">
    <header class="group-head">
        <div>
            <h2 id="{{ $groupId }}-heading">{{ __($group['title']) }}</h2>
            <p class="group-sources">@foreach($group['sources'] as $source)@if(!$loop->first)<span aria-hidden="true">·</span>@endif<span class="group-source" id="source-{{ $source['id'] }}" title="{{ $source['label'] }} · {{ $source['reporting_timezone'] ?? __('Data source reporting timezone') }}@if($source['last_success_at']) · {{ __('Synced :time', ['time' => $source['last_success_at']]) }}@endif">{{ $providerNames[$source['kind']] ?? $source['kind'] }}@if($multiple && $group['key'] !== 'page_speed') · {{ $source['label'] }}@endif @if(!$source['enabled'])<span class="quiet-tag">{{ __('Paused') }}</span>@elseif($source['status'] === 'pending')<span class="quiet-tag">{{ __('Importing') }}</span>@elseif($source['status'] === 'failed')<span class="quiet-tag is-warning">{{ __('Needs attention') }}</span>@endif</span>@endforeach</p>
        </div>
        @if($charts->isNotEmpty())
        <div class="chart-legend" aria-label="{{ __('Chart series') }}"><button type="button" wire:ignore data-chart-toggle="0" aria-pressed="true"><span class="legend-swatch"></span>{{ __('Last :days days', ['days' => $days]) }}</button>@if($hasPrevious)<button type="button" wire:ignore data-chart-toggle="1" aria-pressed="true"><span class="legend-swatch legend-swatch-previous"></span>{{ __('Previous period') }}</button>@endif @if($group['events'])<span class="legend-events"><span class="legend-swatch legend-swatch-event"></span>{{ trans_choice('{1} :count event|[2,*] :count events', $group['events'], ['count' => $group['events']]) }}</span>@endif</div>
        @endif
    </header>
    @foreach($group['sources'] as $source)@include('metribay::partials/source-notice', ['source' => $source, 'providerName' => $providerNames[$source['kind']] ?? $source['kind']])@endforeach
    @if($group['key'] === 'page_speed')
        @foreach($group['sources'] as $source)@if($source['last_success_at'])@include('metribay::partials/page-speed', ['source' => $source, 'multiple' => $multiple])@endif @endforeach
    @elseif($group['cards'] || $group['lines'])
    <div class="metric-grid">
        @foreach($group['cards'] as $card)
            @include($card['size'] === 'strip' ? 'metribay::partials/metric-strip' : 'metribay::partials/metric', ['card' => $card])
        @endforeach
        @foreach($group['lines'] as $line)
            @php($lineId = 'chart-'.$line['source']['id'].'-'.$line['metric']['key'])
            <div class="metric-line" wire:key="{{ $lineId }}"><b id="{{ $lineId }}-label">{{ __($line['metric']['label']) }}</b>@if($line['label'])<span class="metric-source">{{ $line['label'] }}</span>@endif<span aria-hidden="true">·</span><span>{{ __('No data for this period') }}</span>@include('metribay::partials/chart-hide', ['metric' => $line['metric'], 'sourceId' => $line['source']['id']])</div>
        @endforeach
    </div>
    @endif
</section>
