<section class="settings-section" aria-labelledby="preferences-heading">
    <div class="section-intro"><h2 id="preferences-heading">{{ __('Appearance') }}</h2><p>{{ __('Saved on this device. Changes apply immediately.') }}</p></div>
    <div class="form-stack form-width">
        <fieldset class="theme-fieldset"><legend>{{ __('Color theme') }}</legend><div class="theme-options">
            <label class="theme-option"><input type="radio" name="color-theme" value="light" data-theme-input><span class="theme-preview preview-light"><i></i><i></i><i></i></span><span>{{ __('Light') }}</span></label>
            <label class="theme-option"><input type="radio" name="color-theme" value="dark" data-theme-input><span class="theme-preview preview-dark"><i></i><i></i><i></i></span><span>{{ __('Dark') }}</span></label>
            <label class="theme-option"><input type="radio" name="color-theme" value="system" data-theme-input><span class="theme-preview preview-system"><i></i><i></i><i></i></span><span>{{ __('System') }}</span></label>
        </div></fieldset>
        <div class="form-grid"><div class="field"><label for="number-format">{{ __('Numbers') }}</label><select id="number-format" data-number-preference><option value="full">{{ __('Full numbers · 1,234,567') }}</option><option value="compact">{{ __('Compact · 1.2M') }}</option></select></div><div class="field"><label for="density">{{ __('Overview spacing') }}</label><select id="density" data-density-preference><option value="comfortable">{{ __('Comfortable') }}</option><option value="compact">{{ __('Compact') }}</option></select></div></div>
        <p class="field-hint">{{ __('Metric tables always show exact values. Chart dates use each data source’s reporting timezone.') }}</p>
        <p class="sr-only" aria-live="polite" data-preference-status></p>
    </div>
</section>
