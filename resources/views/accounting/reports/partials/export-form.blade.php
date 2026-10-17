<form class="filters" method="get" action="{{ route($routeName) }}" style="margin-top:10px">
    @foreach (request()->except('columns') as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    @foreach ($exportColumns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button type="submit">{{ __('Export Excel') }}</button></div>
</form>
