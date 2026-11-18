{{-- Names the setup this form belongs to, so a step can continue or start again cleanly when the session's setup has ended. --}}
<input type="hidden" name="setup_project" value="{{ $draft['project_id'] ?? '' }}"><input type="hidden" name="setup_kind" value="{{ $draft['kind'] ?? '' }}">
