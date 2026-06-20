@extends('layouts.app')

@section('content')
<h1>{{ __('Settings') }}</h1>
<form method="post" action="{{ route('settings.update') }}">
    @csrf
    @method('put')
    <div class="form-grid">
        <div><label>{{ __('Currency') }}</label><input name="currency" value="{{ old('currency', $settings['currency']->value ?? 'JOD') }}"></div>
        <div><label>{{ __('Default Granulation Cost/Kg') }}</label><input type="number" step="0.001" name="default_granulation_cost_per_kg" value="{{ old('default_granulation_cost_per_kg', $settings['default_granulation_cost_per_kg']->value ?? 0) }}"></div>
        <div><label>{{ __('High Balance Alert') }}</label><input type="number" step="0.001" name="high_balance_threshold" value="{{ old('high_balance_threshold', $settings['high_balance_threshold']->value ?? 1000) }}"></div>
        <div><label>{{ __('Allow Stock Override') }}</label><select name="allow_stock_override"><option value="0" @selected(($settings['allow_stock_override']->value ?? '0') === '0')>{{ __('No') }}</option><option value="1" @selected(($settings['allow_stock_override']->value ?? '0') === '1')>{{ __('Yes') }}</option></select></div>
    </div>
    <p><button>{{ __('Save Settings') }}</button></p>
</form>
@endsection
