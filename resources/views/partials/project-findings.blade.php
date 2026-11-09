@php
    $routeParams = ['workspace' => $workspace->slug, 'project' => $project->id];
@endphp
<section class="project-findings" aria-labelledby="findings-heading">
    <h2 id="findings-heading">{{ __('Recent findings') }}</h2>
    <p class="field-hint">{{ __('These use the latest checks. They stay current when you look at an older reporting period.') }}</p>
    @forelse($findings as $finding)
    @php
        $actionUrl = $finding['anchor'] === 'hidden-charts'
            ? route('metribay.projects.edit', $routeParams).'#hidden-charts'
            : route('metribay.projects.show', $routeParams + $finding['period']).'#'.$finding['anchor'];
    @endphp
    <article class="finding">
        <h3 class="finding-title">{{ $finding['title'] }}</h3>
        <p class="finding-detail">{{ $finding['evidence'] }}</p>
        @if($finding['extra'])<p class="finding-detail">{{ $finding['extra'] }}</p>@endif
        @if($finding['context'])<p class="finding-context">{{ $finding['context'] }}</p>@endif
        <p class="finding-action"><a class="text-link" href="{{ $actionUrl }}">{{ $finding['action'] }}</a></p>
    </article>
    @empty
    @if($findingsChecked)<p class="finding-note">{{ __('No significant changes in the data we checked.') }}</p>
    @elseif(!$findingNotes)<p class="finding-note">{{ __('No data for this period') }}</p>@endif
    @endforelse
    @foreach($findingNotes as $note)<p class="finding-note">{{ $note }}</p>@endforeach
</section>
