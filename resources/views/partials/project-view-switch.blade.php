<nav class="view-switch" aria-label="{{ __('Project view') }}">
    <a href="{{ $overviewUrl }}" @if($projectView === 'overview') aria-current="page" @endif>{{ __('Overview') }}</a>
    <a href="{{ $metricsUrl }}" @if($projectView === 'metrics') aria-current="page" @endif>{{ __('All metrics') }}</a>
    @foreach($presets as $preset)
        <a href="{{ route('metribay.projects.show', $routeParams + $period->query() + $archive + ['view' => $preset['key']]) }}" @if($activePreset === $preset['key']) aria-current="page" @endif>{{ __($preset['label']) }}</a>
    @endforeach
    @if($availableGroups !== [])
        <details class="view-menu" wire:ignore.self data-project-views data-match="{{ $projectView }}" data-active-groups="{{ $matchGroups }}" data-known-groups="{{ implode(',', array_column($availableGroups, 'key')) }}" data-summary-label="{{ __('Custom view') }}" data-max="{{ \Metribay\Core\Metrics\ProjectViews::MAX_SAVED }}" data-msg-name="{{ __('Enter a view name.') }}" data-msg-groups="{{ __('Choose at least one chart group.') }}" data-msg-limit="{{ __('You can save up to :count views for this project.') }}" data-msg-saved="{{ __('View saved in this browser.') }}" data-msg-renamed="{{ __('View renamed.') }}" data-msg-removed="{{ __('View removed.') }}" data-msg-taken="{{ __('A view with this name is already saved.') }}">
            <summary @if($projectView === 'custom') aria-current="page" @endif>{{ __('Custom view') }}</summary>
            <div class="view-menu-panel">
                <p class="view-saved-heading" data-saved-heading hidden>{{ __('Saved views') }}</p>
                <div class="view-saved" data-saved-views></div>
                <p class="field-hint" data-saved-empty>{{ __('No saved views yet.') }}</p>
                <form method="GET" action="{{ route('metribay.projects.show', $routeParams) }}" data-custom-view-form>
                    @foreach($period->query() as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    @if($queryMonth)<input type="hidden" name="queries" value="{{ $queryMonth }}">@endif
                    @if($queryCompare)<input type="hidden" name="compare" value="{{ $queryCompare }}">@endif
                    <fieldset class="view-menu-groups">
                        <legend>{{ __('Chart groups') }}</legend>
                        @foreach($availableGroups as $group)
                            <label><input type="checkbox" name="groups[]" value="{{ $group['key'] }}" data-group @checked(in_array($group['key'], $checkedGroups, true))> {{ __($group['title']) }}</label>
                        @endforeach
                    </fieldset>
                    <div class="view-menu-actions">
                        <button class="button button-secondary button-small" type="submit">{{ __('Show') }}</button>
                    </div>
                    <label class="view-name"><span>{{ __('View name') }}</span><input type="text" maxlength="40" data-view-name autocomplete="off" spellcheck="false"></label>
                    <div class="view-menu-actions">
                        <button class="button button-secondary button-small" type="button" data-view-save>{{ __('Save view') }}</button>
                    </div>
                    <p class="field-hint" data-view-status role="status"></p>
                </form>
                <template data-saved-view-template>
                    <div class="view-saved-row" data-saved-row>
                        <a class="text-link" data-saved-link href=""></a>
                        <input data-saved-input hidden type="text" maxlength="40" autocomplete="off" spellcheck="false" aria-label="{{ __('View name') }}">
                        <button type="button" class="button button-small button-secondary" data-saved-rename>{{ __('Rename view') }}</button>
                        <button type="button" class="button button-small button-secondary" data-saved-rename-save hidden>{{ __('Save view') }}</button>
                        <button type="button" class="button button-small button-secondary" data-saved-remove>{{ __('Remove view') }}</button>
                    </div>
                </template>
            </div>
        </details>
    @endif
</nav>
